<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Export;

use App\Models\Conversation;
use App\Models\FacebookInteraction;
use App\Models\Friendship;
use App\Models\ImportSession;
use App\Models\InteractionEdge;
use App\Models\Message;
use App\Models\SocialPerson;
use Illuminate\Support\Facades\DB;

final class AnalyticsWorkbookExporter
{
    public function create(int $userId, string $target): void
    {
        $book = new OpenXmlWorkbook;
        $summary = [['Generated at', now()->utc()->toIso8601String()], ['Privacy mode', 'Message content excluded'], ['Friends', Friendship::where('user_id', $userId)->where('status', 'current')->count()], ['People', SocialPerson::where('user_id', $userId)->count()], ['Direct conversations', Conversation::where('user_id', $userId)->where('type', 'direct')->count()], ['Group conversations', Conversation::where('user_id', $userId)->where('type', 'group')->count()], ['Total messages', Message::where('user_id', $userId)->count()], ['Messages sent', InteractionEdge::where('user_id', $userId)->sum('messages_sent')], ['Messages received', InteractionEdge::where('user_id', $userId)->sum('messages_received')], ['Facebook interactions', FacebookInteraction::where('user_id', $userId)->count()]];
        $book->addSheet('Summary', ['Metric', 'Value'], $summary);
        $book->addSheet('People', $this->peopleHeader(), $this->people($userId));
        $book->addSheet('Conversation Sessions', ['Person', 'Sessions', 'Started By User', 'Started By Person'], $this->sessions($userId));
        $book->addSheet('Groups', ['Group', 'Participants', 'Messages', 'First Message', 'Last Message'], $this->groups($userId));
        $book->addSheet('Facebook Interactions', ['Type', 'Object Type', 'Occurred At', 'Weight'], $this->facebook($userId));
        $book->addSheet('Interaction Scores', ['Person', 'Frequency', 'Active Days', 'Recency', 'Mutuality', 'Initiation', 'Facebook', 'Overall'], $this->scores($userId));
        $book->addSheet('Monthly Trends', ['Person', 'Month', 'Messages Sent', 'Messages Received', 'Interactions'], $this->monthly($userId));
        $book->addSheet('Data Coverage', ['Import', 'Status', 'Dataset', 'Availability'], $this->coverage($userId));
        $book->save($target);
    }

    private function peopleHeader(): array
    {
        return ['Name', 'Messages Sent', 'Messages Received', 'Messages Total', 'Active Days', 'Conversation Sessions', 'Sessions Started By User', 'Sessions Started By Person', 'Initiation Percentage', 'Frequency Score', 'Active Day Score', 'Recency Score', 'Mutuality Score', 'Facebook Score', 'Overall Score', 'First Interaction', 'Last Interaction', '30 Day Trend'];
    }

    private function people(int $u): \Generator
    {
        foreach (InteractionEdge::with('person')->where('user_id', $u)->orderByDesc('overall_score')->cursor() as $e) {
            $total = $e->session_count;
            yield [$e->person->display_name, $e->messages_sent, $e->messages_received, $e->messages_sent + $e->messages_received, $e->active_days, $total, $e->sessions_started_by_user, $e->sessions_started_by_person, $total ? round(100 * $e->sessions_started_by_person / $total, 2) : 0, $e->frequency_score, $e->active_day_score, $e->recency_score, $e->mutuality_score, $e->facebook_score, $e->overall_score, $e->first_interaction_at?->toIso8601String(), $e->last_interaction_at?->toIso8601String(), $e->trend];
        }
    }

    private function sessions(int $u): \Generator
    {
        foreach (InteractionEdge::with('person')->where('user_id', $u)->cursor() as $e) {
            yield [$e->person->display_name, $e->session_count, $e->sessions_started_by_user, $e->sessions_started_by_person];
        }
    }

    private function groups(int $u): \Generator
    {
        foreach (Conversation::where('user_id', $u)->where('type', 'group')->cursor() as $g) {
            yield [$g->title, $g->participant_count, $g->message_count, $g->first_message_at?->toIso8601String(), $g->last_message_at?->toIso8601String()];
        }
    }

    private function facebook(int $u): \Generator
    {
        foreach (FacebookInteraction::where('user_id', $u)->orderBy('occurred_at')->cursor() as $x) {
            yield [$x->interaction_type, $x->object_type, $x->occurred_at?->toIso8601String(), $x->weight];
        }
    }

    private function scores(int $u): \Generator
    {
        foreach (InteractionEdge::with('person')->where('user_id', $u)->cursor() as $e) {
            yield [$e->person->display_name, $e->frequency_score, $e->active_day_score, $e->recency_score, $e->mutuality_score, $e->initiation_score, $e->facebook_score, $e->overall_score];
        }
    }

    private function monthly(int $u): \Generator
    {
        $driver = DB::connection()->getDriverName();
        $period = $driver === 'sqlite' ? "strftime('%Y-%m', d.date)" : "to_char(d.date, 'YYYY-MM')";
        $rows = DB::table('daily_interaction_stats as d')->join('social_people as p', 'p.id', '=', 'd.social_person_id')->where('d.user_id', $u)->selectRaw("p.display_name, $period period, sum(messages_sent) sent, sum(messages_received) received, sum(interaction_count) interactions")->groupByRaw("p.display_name, $period")->orderBy('period')->cursor();
        foreach ($rows as $r) {
            yield [$r->display_name, $r->period, $r->sent, $r->received, $r->interactions];
        }
    }

    private function coverage(int $u): \Generator
    {
        foreach (ImportSession::where('user_id', $u)->cursor() as $i) {
            foreach (($i->data_coverage ?? []) as $d => $v) {
                yield [$i->original_filename, $i->status, $d, $v];
            }
        }
    }
}
