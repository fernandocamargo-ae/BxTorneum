<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamRequest;
use App\Models\Member;
use App\Models\Team;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index()
    {
        $myTeamId = Member::where('user_id', auth()->id())->value('team_id');

        $teams = Team::withCount('members')->orderBy('name')->get()->map(fn (Team $team) => [
            'id' => $team->id,
            'name' => $team->name,
            'members_count' => $team->members_count,
            'has_room' => $team->members_count < 3,
        ]);

        return Inertia::render('Teams/Index', ['teams' => $teams, 'my_team_id' => $myTeamId]);
    }

    public function create()
    {
        return Inertia::render('Teams/Create');
    }

    public function store(TeamRequest $request)
    {
        $userId = auth()->id();

        abort_if(
            Member::where('user_id', $userId)->exists(),
            422,
            'Ya perteneces a un equipo. Sal del actual antes de crear otro.'
        );

        $team = Team::create(['name' => $request->validated('name'), 'created_by_user_id' => $userId]);
        $team->members()->create(['user_id' => $userId, 'role' => 'captain']);

        return redirect()->route('teams.show', $team)->with('success', 'Equipo creado. Eres el capitán.');
    }

    public function join(Team $team)
    {
        $user = auth()->user();

        abort_if(Member::where('user_id', $user->id)->exists(), 422, 'Ya perteneces a un equipo. Sal del actual antes de unirte a otro.');

        $role = $team->firstOpenRole();
        abort_unless($role, 422, 'El equipo ya está completo.');

        $team->members()->create(['user_id' => $user->id, 'role' => $role]);

        return back()->with('success', "Te uniste al equipo \"{$team->name}\".");
    }

    public function leave(Team $team)
    {
        $member = $team->memberFor(auth()->user());
        abort_unless($member, 422, 'No perteneces a este equipo.');
        abort_if($team->isLockedByActiveTournament(), 422, 'El equipo está inscrito en un torneo en curso; no puedes salir hasta que termine.');

        $wasCaptain = $member->role === 'captain';
        $member->delete();

        if ($team->members()->count() === 0) {
            $team->delete();
        } elseif ($wasCaptain) {
            $team->promoteOldestToCaptain();
        }

        return redirect()->route('teams.index')->with('success', 'Saliste del equipo.');
    }

    public function show(Team $team)
    {
        return Inertia::render('Teams/Show', [
            'team' => $this->teamPayload($team),
            'viewer' => $this->viewerFlags($team),
        ]);
    }

    private function teamPayload(Team $team): array
    {
        $roleOrder = ['captain' => 0, 'subcaptain' => 1, 'official' => 2];

        $members = $team->members()->with('user:id,nickname,avatar_path')->get()
            ->sortBy(fn (Member $member) => $roleOrder[$member->role] ?? 9)
            ->values()
            ->map(fn (Member $member) => [
                'id' => $member->id,
                'role' => $member->role,
                'nickname' => $member->user?->nickname,
                'avatar_url' => $member->user?->avatar_url,
                'has_tournament_deck' => (bool) $member->user?->decks()
                    ->where('is_tournament_deck', true)->whereHas('deckBeyblades')->exists(),
            ]);

        return [
            'id' => $team->id,
            'name' => $team->name,
            'members' => $members,
            'has_room' => $team->hasRoom(),
            'is_tournament_ready' => $team->isTournamentReady(),
        ];
    }

    private function viewerFlags(Team $team): array
    {
        $user = auth()->user();
        $myMember = $user ? $team->memberFor($user) : null;
        $inAnyTeam = $user ? Member::where('user_id', $user->id)->exists() : false;

        return [
            'is_member' => (bool) $myMember,
            'is_captain' => $myMember?->role === 'captain',
            'can_join' => $user && ! $inAnyTeam && $team->hasRoom(),
            'locked_by_tournament' => $team->isLockedByActiveTournament(),
        ];
    }
}
