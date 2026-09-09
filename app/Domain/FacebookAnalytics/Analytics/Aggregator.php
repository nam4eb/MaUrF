<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Analytics;

use App\Models\Conversation;
use App\Models\FacebookInteraction;
use App\Models\InteractionEdge;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Aggregator
{
    public function rebuild(int $userId, ?string $currentImportId = null): void
    {
        DB::table('daily_interaction_stats')->where('user_id', $userId)->delete();
        InteractionEdge::where('user_id', $userId)->delete();
        $metrics = [];
        $daily = [];
        $valid = $this->validImports($userId, $currentImportId);
        Conversation::where('user_id', $userId)->where('type', 'direct')->whereIn('source_import_id', $valid)->with('participants')->each(function ($conversation) use (&$metrics, &$daily, $userId) {
            $owner = $conversation->participants->firstWhere('pivot.is_owner', true);
            $other = $conversation->participants->firstWhere('pivot.is_owner', false);
            if (! $owner || ! $other) {
                return;
            }$previous = null;
            foreach (Message::where('conversation_id', $conversation->id)->orderBy('sent_at')->cursor() as $m) {
                $x = &$this->metric($metrics, $other->id, $m->sent_at);
                $isOwner = $m->sender_person_id === $owner->id;
                $x[$isOwner ? 'sent' : 'received']++;
                $x['days'][$m->sent_at->toDateString()] = true;
                $x['first'] = $x['first'] ? min($x['first'], $m->sent_at) : $m->sent_at;
                $x['last'] = $x['last'] ? max($x['last'], $m->sent_at) : $m->sent_at;
                $age = $m->sent_at->diffInDays(now(), false);
                if ($age >= 0 && $age < 30) {
                    $x['last30']++;
                } elseif ($age >= 30 && $age < 60) {
                    $x['prev30']++;
                }if ($age >= 0 && $age < 90) {
                    $x['last90']++;
                } elseif ($age >= 90 && $age < 180) {
                    $x['prev90']++;
                }if (! $previous || $previous->diffInHours($m->sent_at) >= config('facebook_analytics.conversation_gap_hours', 8)) {
                    $x['sessions']++;
                    $x[$isOwner ? 'user_sessions' : 'person_sessions']++;
                }$previous = $m->sent_at;
                $key = $other->id.'|'.$m->sent_at->toDateString();
                $d = &$daily[$key];
                $d ??= $this->daily($userId, $other->id, $m->sent_at->toDateString());
                $d[$isOwner ? 'messages_sent' : 'messages_received']++;
                $d['interaction_count']++;
            }
        });
        foreach (FacebookInteraction::where('user_id', $userId)->whereIn('source_import_id', $valid)->whereNotNull('source_person_id')->cursor() as $i) {
            $x = &$this->metric($metrics, $i->source_person_id, $i->occurred_at);
            $x['facebook'] += (float) $i->weight;
            if ($i->occurred_at) {
                $x['days'][$i->occurred_at->toDateString()] = true;
                $x['first'] = $x['first'] ? min($x['first'], $i->occurred_at) : $i->occurred_at;
                $x['last'] = $x['last'] ? max($x['last'], $i->occurred_at) : $i->occurred_at;
                $key = $i->source_person_id.'|'.$i->occurred_at->toDateString();
                $d = &$daily[$key];
                $d ??= $this->daily($userId, $i->source_person_id, $i->occurred_at->toDateString());
                if (isset($d[$i->interaction_type.'s'])) {
                    $d[$i->interaction_type.'s']++;
                }$d['interaction_count']++;
            }
        }
        foreach (array_chunk(array_values($daily), 500) as $rows) {
            DB::table('daily_interaction_stats')->insert($rows);
        }$max = max(array_map(fn ($x) => $x['sent'] + $x['received'], $metrics) ?: [0]);
        $maxDays = max(array_map(fn ($x) => count($x['days']), $metrics) ?: [0]);
        $maxFacebook = max(array_column($metrics, 'facebook') ?: [0]);
        $facebookAvailable = $maxFacebook > 0;
        foreach ($metrics as $person => $x) {
            if (! $x['last']) {
                continue;
            }$frequency = InteractionScore::frequency($x['sent'] + $x['received'], $max);
            $active = InteractionScore::frequency(count($x['days']), $maxDays);
            $recency = InteractionScore::recency((int) $x['last']->diffInDays(now()), config('facebook_analytics.recency_decay_days'));
            $mutuality = InteractionScore::mutuality($x['sent'], $x['received']);
            $initiation = InteractionScore::mutuality($x['user_sessions'], $x['person_sessions']);
            $facebook = InteractionScore::frequency((int) $x['facebook'], (int) $maxFacebook);
            $scores = ['frequency' => $frequency, 'active_days' => $active, 'recency' => $recency, 'mutuality' => $mutuality, 'initiation' => $initiation];
            if ($facebookAvailable) {
                $scores['facebook'] = $facebook;
            }$t30 = $this->trend($x['last30'], $x['prev30']);
            $t90 = $this->trend($x['last90'], $x['prev90']);
            InteractionEdge::create(['id' => (string) Str::uuid(), 'user_id' => $userId, 'social_person_id' => $person, 'messages_sent' => $x['sent'], 'messages_received' => $x['received'], 'sessions_started_by_user' => $x['user_sessions'], 'sessions_started_by_person' => $x['person_sessions'], 'session_count' => $x['sessions'], 'active_days' => count($x['days']), 'first_interaction_at' => $x['first'], 'last_interaction_at' => $x['last'], 'frequency_score' => $frequency, 'active_day_score' => $active, 'recency_score' => $recency, 'mutuality_score' => $mutuality, 'initiation_score' => $initiation, 'facebook_score' => $facebook, 'overall_score' => InteractionScore::overall($scores, config('facebook_analytics.overall_weights')), 'trend' => $t30[0], 'trend_percent' => $t30[1], 'trend_90' => $t90[0], 'trend_90_percent' => $t90[1], 'computed_at' => now()]);
        }
    }

    private function validImports(int $u, ?string $current)
    {
        return DB::table('import_sessions')->select('id')->where('user_id', $u)->where(function ($q) use ($current) {
            $q->whereIn('status', ['completed', 'completed_with_warnings']);
            if ($current) {
                $q->orWhere('id', $current);
            }
        });
    }

    private function &metric(array &$all, string $id, $at): array
    {
        $all[$id] ??= ['sent' => 0, 'received' => 0, 'user_sessions' => 0, 'person_sessions' => 0, 'sessions' => 0, 'days' => [], 'first' => $at, 'last' => $at, 'last30' => 0, 'prev30' => 0, 'last90' => 0, 'prev90' => 0, 'facebook' => 0.0];

        return $all[$id];
    }

    private function daily(int $u, string $p, string $date): array
    {
        return ['user_id' => $u, 'social_person_id' => $p, 'date' => $date, 'messages_sent' => 0, 'messages_received' => 0, 'conversations_started_by_user' => 0, 'conversations_started_by_person' => 0, 'reactions' => 0, 'comments' => 0, 'mentions' => 0, 'tags' => 0, 'interaction_count' => 0];
    }

    private function trend(int $current, int $previous): array
    {
        if ($current === 0) {
            return ['inactive', null];
        }if ($previous === 0) {
            return ['rapidly_increasing', 100.0];
        }$p = round(($current - $previous) * 100 / $previous, 2);
        $rapid = config('facebook_analytics.trend_thresholds.rapid', 50);
        $change = config('facebook_analytics.trend_thresholds.change',15);

        return [$p >= $rapid ? 'rapidly_increasing' : ($p >= $change ? 'increasing' : ($p <= -$rapid ? 'rapidly_decreasing' : ($p <= -$change ? 'decreasing' : 'stable'))), $p];
    }
}
