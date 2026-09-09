<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ImportSession;
use App\Models\InteractionEdge;
use App\Models\Message;
use App\Models\SocialPerson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AnalyticsController extends Controller
{
    private function envelope($data, array $meta = []): JsonResponse
    {
        return response()->json(compact('data', 'meta') + ['error' => null]);
    }

    public function overview(Request $r): JsonResponse
    {
        $u = $r->user()->id;

        return $this->envelope(['people' => SocialPerson::where('user_id', $u)->count(), 'direct_conversations' => Conversation::where('user_id', $u)->where('type', 'direct')->count(), 'group_conversations' => Conversation::where('user_id', $u)->where('type', 'group')->count(), 'total_messages' => Message::where('user_id', $u)->count(), 'messages_sent' => (int) InteractionEdge::where('user_id', $u)->sum('messages_sent'), 'messages_received' => (int) InteractionEdge::where('user_id', $u)->sum('messages_received'), 'active_people_30_days' => InteractionEdge::where('user_id', $u)->where('last_interaction_at', '>=', now()->subDays(30))->count(), 'top_people' => InteractionEdge::with('person')->where('user_id', $u)->orderByDesc('overall_score')->limit(10)->get()]);
    }

    public function people(Request $r): JsonResponse
    {
        $q = InteractionEdge::with('person')->where('user_id', $r->user()->id);
        if ($s = $r->string('search')->toString()) {
            $q->whereHas('person', fn ($x) => $x->where('display_name', 'like', '%'.$s.'%'));
        }$allowed = ['overall_score', 'active_days', 'mutuality_score', 'last_interaction_at', 'messages_sent'];
        $sort = in_array($r->input('sort'), $allowed) ? $r->input('sort') : 'overall_score';

        return $this->envelope($q->orderBy($sort, $r->input('direction') === 'asc' ? 'asc' : 'desc')->paginate(min((int) $r->input('per_page', 25), 100)));
    }

    public function person(Request $r, string $id): JsonResponse
    {
        $edge = InteractionEdge::with('person')->where('user_id', $r->user()->id)->where('social_person_id', $id)->firstOrFail();
        $facebook = DB::table('facebook_interactions')->where('user_id', $r->user()->id)->where(fn ($q) => $q->where('source_person_id', $id)->orWhere('target_person_id', $id))->exists();
        $components = ['frequency' => (float) $edge->frequency_score, 'active_days' => (float) $edge->active_day_score, 'recency' => (float) $edge->recency_score, 'mutuality' => (float) $edge->mutuality_score, 'initiation' => (float) $edge->initiation_score];
        if ($facebook) {
            $components['facebook'] = (float) $edge->facebook_score;
        }$total = $edge->messages_sent + $edge->messages_received;
        $insights = [];
        if ($edge->session_count) {
            $insights[] = round(100 * $edge->sessions_started_by_person / $edge->session_count).'% of conversation sessions were initiated by '.$edge->person->display_name.'.';
        }if ($total) {
            $insights[] = 'Message balance: '.round(100 * $edge->messages_sent / $total).'% sent and '.round(100 * $edge->messages_received / $total).'% received.';
        }if ($edge->trend_percent !== null) {
            $insights[] = 'Interaction changed '.abs((float) $edge->trend_percent).'% versus the previous 30-day period ('.$edge->trend.').';
        }

        return $this->envelope(['edge' => $edge, 'score' => ['overall' => (float) $edge->overall_score, 'components' => $components, 'available_dimensions' => array_keys($components)], 'insights' => $insights]);
    }

    public function timeline(Request $r, string $id): JsonResponse
    {
        $this->owned($r, $id);
        $driver = DB::connection()->getDriverName();
        $period = $driver === 'sqlite' ? "strftime('%Y-%m', date)" : "to_char(date, 'YYYY-MM')";
        $rows = DB::table('daily_interaction_stats')->where('user_id', $r->user()->id)->where('social_person_id', $id)->selectRaw("$period as period, sum(messages_sent + messages_received) messages, sum(interaction_count) interactions")->groupByRaw($period)->orderBy('period')->get();

        return $this->envelope($rows);
    }

    public function heatmap(Request $r, string $id): JsonResponse
    {
        $this->owned($r, $id);
        $driver = DB::connection()->getDriverName();
        $day = $driver === 'sqlite' ? "cast(strftime('%w', messages.sent_at) as integer)" : 'extract(dow from messages.sent_at)';
        $hour = $driver === 'sqlite' ? "cast(strftime('%H', messages.sent_at) as integer)" : 'extract(hour from messages.sent_at)';
        $rows = DB::table('messages')
            ->join('conversation_participants as cp', 'cp.conversation_id', '=', 'messages.conversation_id')
            ->where('messages.user_id', $r->user()->id)
            ->where('cp.social_person_id', $id)
            ->selectRaw("$day day, $hour hour, count(*) count")
            ->groupByRaw("$day, $hour")
            ->get();

        return $this->envelope($rows);
    }

    public function network(Request $r): JsonResponse
    {
        $limit = min((int) $r->input('limit', 25), 100);
        $edges = InteractionEdge::with('person')->where('user_id', $r->user()->id)->orderByDesc('overall_score')->limit($limit)->get();

        return $this->envelope(['nodes' => collect([['id' => 'owner', 'name' => 'You', 'value' => (int) $edges->sum(fn ($e) => $e->messages_sent + $e->messages_received)]])->concat($edges->map(fn ($e) => ['id' => $e->social_person_id, 'name' => $e->person->display_name, 'value' => $e->messages_sent + $e->messages_received])), 'links' => $edges->map(fn ($e) => ['source' => 'owner', 'target' => $e->social_person_id, 'value' => (float) $e->overall_score])]);
    }

    public function groups(Request $r): JsonResponse
    {
        return $this->envelope(Conversation::where('user_id', $r->user()->id)->where('type', 'group')->orderByDesc('message_count')->paginate(25));
    }

    public function group(Request $r, string $id): JsonResponse
    {
        $group = $this->ownedGroup($r, $id);
        $participants = DB::table('conversation_participants as cp')->join('social_people as p', 'p.id', '=', 'cp.social_person_id')->leftJoin('messages as m', function ($j) {
            $j->on('m.conversation_id', '=', 'cp.conversation_id')->on('m.sender_person_id', '=', 'cp.social_person_id');
        })->where('cp.conversation_id', $id)->groupBy('p.id', 'p.display_name')->select('p.id', 'p.display_name', DB::raw('count(m.id) as messages'), DB::raw('count(distinct DATE(m.sent_at)) as active_days'), DB::raw('min(m.sent_at) as first_message_at'), DB::raw('max(m.sent_at) as last_message_at'))->orderByDesc('messages')->paginate(min((int) $r->input('per_page', 50), 100));
        foreach ($participants as $p) {
            $p->share_of_messages = $group->message_count ? round(100 * $p->messages / $group->message_count, 2) : 0;
        }

        return $this->envelope(['group' => $group, 'participants' => $participants]);
    }

    public function groupTimeline(Request $r, string $id): JsonResponse
    {
        $this->ownedGroup($r, $id);
        $driver = DB::connection()->getDriverName();
        $period = $driver === 'sqlite' ? "strftime('%Y-%m', sent_at)" : "to_char(sent_at, 'YYYY-MM')";
        $rows = DB::table('messages')->where('user_id', $r->user()->id)->where('conversation_id', $id)->selectRaw("$period period, count(*) messages")->groupByRaw($period)->orderBy('period')->get();

        return $this->envelope($rows);
    }

    public function groupHeatmap(Request $r, string $id): JsonResponse
    {
        $this->ownedGroup($r, $id);
        $driver = DB::connection()->getDriverName();
        $day = $driver === 'sqlite' ? "cast(strftime('%w', sent_at) as integer)" : 'extract(dow from sent_at)';
        $hour = $driver === 'sqlite' ? "cast(strftime('%H', sent_at) as integer)" : 'extract(hour from sent_at)';
        $rows = DB::table('messages')->where('user_id', $r->user()->id)->where('conversation_id', $id)->selectRaw("$day day, $hour hour, count(*) count")->groupByRaw("$day, $hour")->get();

        return $this->envelope($rows);
    }

    public function deleteAll(Request $r): JsonResponse
    {
        $u = $r->user()->id;
        $imports = ImportSession::where('user_id', $u)->pluck('id');
        $exports = DB::table('analytics_exports')->where('user_id', $u)->pluck('storage_path')->filter();
        DB::transaction(function () use ($u) {
            ImportSession::where('user_id', $u)->delete();
            SocialPerson::where('user_id', $u)->delete();
            DB::table('analytics_exports')->where('user_id', $u)->delete();
        });
        foreach ($imports as $id) {
            Storage::deleteDirectory('private/imports/'.$id);
        }Storage::delete($exports->all());

        return $this->envelope(['deleted' => true]);
    }

    private function owned(Request $r, string $id): void
    {
        SocialPerson::where('user_id', $r->user()->id)->findOrFail($id);
    }

    private function ownedGroup(Request $r, string $id): Conversation
    {
        return Conversation::where('user_id', $r->user()->id)->where('type', 'group')->findOrFail($id);
    }
}
