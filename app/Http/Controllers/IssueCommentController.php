<?php

namespace App\Http\Controllers;

use App\Enums\IssueActivityType;
use App\Http\Requests\StoreIssueCommentRequest;
use App\Models\Issue;
use App\Models\IssueActivity;
use App\Models\User;
use App\Notifications\IssueMentionedNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class IssueCommentController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreIssueCommentRequest $request, Issue $issue): RedirectResponse
    {
        $this->authorize('view', $issue);

        $body = $request->validated()['body'];

        IssueActivity::create([
            'issue_id' => $issue->id,
            'user_id' => $request->user()->id,
            'type' => IssueActivityType::Comment,
            'body' => $body,
            'created_at' => now(),
        ]);

        $this->notifyMentionedUsers($issue, $body, $request->user());

        return redirect()->route('issues.show', $issue)->with('success', 'Comment added.');
    }

    private function notifyMentionedUsers(Issue $issue, string $body, User $actor): void
    {
        preg_match_all('/\B@([\w]+)/u', $body, $matches);

        if (empty($matches[1])) {
            return;
        }

        $firstNames = array_unique($matches[1]);

        User::query()
            ->where('agency_id', $issue->agency_id)
            ->where('id', '!=', $actor->id)
            ->get()
            ->filter(function (User $user) use ($firstNames): bool {
                $firstName = explode(' ', $user->name)[0];

                return in_array($firstName, $firstNames, strict: true);
            })
            ->each(function (User $user) use ($issue, $actor, $body): void {
                $user->notify(new IssueMentionedNotification($issue, $actor, $body));
            });
    }
}
