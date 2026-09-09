<?php

declare(strict_types=1);

namespace App\Domain\FacebookAnalytics\Import;

use App\Models\ImportSession;
use App\Models\SocialPerson;
use Illuminate\Support\Facades\DB;

final class OwnerIdentityResolver
{
    public function finalize(ImportSession $import, ?string $profileName = null): void
    {
        $candidates = SocialPerson::where('user_id', $import->user_id)->where('source', 'messenger')->whereHas('conversations', fn ($q) => $q->where('source_import_id', $import->id))->get();
        DB::table('owner_identity_candidates')->where('import_session_id', $import->id)->delete();
        foreach ($candidates as $c) {
            DB::table('owner_identity_candidates')->insert(['import_session_id' => $import->id, 'social_person_id' => $c->id, 'signal' => $profileName && $c->normalized_name === mb_strtolower(trim($profileName)) ? 'profile_name_match' : 'conversation_participant', 'confidence' => $profileName && $c->normalized_name === mb_strtolower(trim($profileName)) ? 90 : 20, 'created_at' => now(), 'updated_at' => now()]);
        }$matches = $profileName ? $candidates->filter(fn ($c) => $c->normalized_name === mb_strtolower(trim($profileName))) : collect();
        if ($matches->count() === 1) {
            $this->resolve($import, $matches->first());
        } else {
            $import->update(['owner_resolution_status' => $candidates->isEmpty() ? 'unresolved' : 'needs_confirmation', 'owner_person_id' => null]);
        }
    }

    public function resolve(ImportSession $import, SocialPerson $person): void
    {
        abort_unless($person->user_id === $import->user_id, 404);
        $import->update(['owner_person_id' => $person->id, 'owner_resolution_status' => 'resolved']);
        DB::table('conversation_participants')->whereIn('conversation_id', DB::table('conversations')->where('source_import_id', $import->id)->select('id'))->update(['is_owner' => false]);
        DB::table('conversation_participants')->where('social_person_id', $person->id)->whereIn('conversation_id', DB::table('conversations')->where('source_import_id', $import->id)->select('id'))->update(['is_owner' => true]);
    }
}
