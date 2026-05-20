<?php

use App\Models\Submission;
use App\Models\SubmissionComment;
use App\Models\SubmissionLike;
use App\Notifications\SubmissionCommentedNotification;
use App\Notifications\SubmissionLikedNotification;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Artwork')] class extends Component {
    public Submission $submission;

    public string $newComment = '';

    public function mount(Submission $submission): void
    {
        $this->submission = $submission->load([
            'userChallenge.user',
            'userChallenge.challenge:id,title,is_private,creator_id,has_palette,palette_name,palette_colors',
            'challengeDay:id,day_number,prompt_text',
        ]);

        if ($this->submission->userChallenge->challenge->is_private
            && $this->submission->userChallenge->user_id !== Auth::id()) {
            abort(404);
        }
    }

    #[Computed]
    public function comments()
    {
        return $this->submission->comments()
            ->with('user')
            ->latest()
            ->get();
    }

    #[Computed]
    public function likesCount(): int
    {
        return $this->submission->likes()->count();
    }

    #[Computed]
    public function commentsCount(): int
    {
        return $this->submission->comments()->count();
    }

    #[Computed]
    public function isLiked(): bool
    {
        return $this->submission->isLikedBy(Auth::user());
    }

    public function toggleLike(): void
    {
        $existing = SubmissionLike::where('user_id', Auth::id())
            ->where('submission_id', $this->submission->id)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            SubmissionLike::create([
                'user_id' => Auth::id(),
                'submission_id' => $this->submission->id,
            ]);

            $owner = $this->submission->userChallenge->user;
            if ($owner->id !== Auth::id()) {
                $owner->notify(new SubmissionLikedNotification(Auth::user(), $this->submission));
            }
        }

        unset($this->isLiked, $this->likesCount);
    }

    public function addComment(): void
    {
        $validated = $this->validate([
            'newComment' => ['required', 'string', 'max:500'],
        ]);

        SubmissionComment::create([
            'user_id' => Auth::id(),
            'submission_id' => $this->submission->id,
            'body' => $validated['newComment'],
        ]);

        $owner = $this->submission->userChallenge->user;
        if ($owner->id !== Auth::id()) {
            $owner->notify(new SubmissionCommentedNotification(Auth::user(), $this->submission));
        }

        $this->newComment = '';
        unset($this->comments, $this->commentsCount);
    }

    public function deleteComment(int $commentId): void
    {
        $comment = SubmissionComment::where('id', $commentId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $comment->delete();
        unset($this->comments, $this->commentsCount);
    }

}; ?>

<div>
    <div class="mx-auto w-full max-w-4xl space-y-5 p-6">
        <flux:text size="sm">
            <a href="{{ route('challenges.template', $submission->userChallenge->challenge) }}" wire:navigate class="hover:underline opacity-70">
                ← {{ $submission->userChallenge->challenge->title }}
            </a>
        </flux:text>

        <figure class="space-y-3">
            <img
                src="/storage/{{ $submission->image_path }}"
                alt="{{ $submission->caption ?? $submission->challengeDay->prompt_text }}"
                class="w-full rounded-lg"
            />
            <figcaption class="space-y-2">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">
                            {{ __('Day :n', ['n' => $submission->challengeDay->day_number]) }} · {{ $submission->challengeDay->prompt_text }}
                        </flux:heading>
                        <flux:text class="opacity-70">
                            <x-user-link :user="$submission->userChallenge->user" :with-avatar="true" />
                            · {{ $submission->created_at->diffForHumans() }}
                        </flux:text>
                    </div>
                    <flux:button
                        size="sm"
                        :variant="$this->isLiked ? 'primary' : 'ghost'"
                        icon="heart"
                        wire:click="toggleLike"
                    >
                        {{ $this->likesCount }}
                    </flux:button>
                </div>
                @if ($submission->caption)
                    <flux:text class="italic opacity-80">{{ $submission->caption }}</flux:text>
                @endif

                @php $challenge = $submission->userChallenge->challenge; @endphp
                @if ($challenge->has_palette && $challenge->palette_colors)
                    <div class="space-y-2 rounded-lg border border-zinc-700 p-3">
                        <flux:text size="sm" class="opacity-70">
                            @if ($challenge->palette_name)
                                {{ $challenge->palette_name }} ·
                            @endif
                            {{ __('click a color to copy') }}
                        </flux:text>
                        <x-palette-preview :colors="$challenge->palette_colors" size="lg" :clickable="true" />
                    </div>
                @endif
            </figcaption>
        </figure>

        <flux:separator />

        <section class="space-y-4">
            <flux:heading size="lg">
                {{ __('Comments') }}
                <span class="opacity-50">({{ $this->commentsCount }})</span>
            </flux:heading>

            <form wire:submit="addComment" class="space-y-2">
                <flux:textarea
                    wire:model="newComment"
                    :placeholder="__('Share your thoughts...')"
                    rows="2"
                    maxlength="500"
                />
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" size="sm">
                        {{ __('Post comment') }}
                    </flux:button>
                </div>
            </form>

            @if ($this->comments->isEmpty())
                <flux:text class="opacity-60">{{ __('No comments yet. Be the first!') }}</flux:text>
            @else
                <ul class="space-y-3">
                    @foreach ($this->comments as $comment)
                        <li class="rounded-lg border border-zinc-700 bg-zinc-900 p-4">
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <x-user-link :user="$comment->user" :with-avatar="true" class="text-sm font-semibold" />
                                    <flux:text size="xs" class="opacity-50">{{ $comment->created_at->diffForHumans() }}</flux:text>
                                </div>
                                @if ($comment->user_id === Auth::id())
                                    <flux:button
                                        variant="ghost"
                                        size="xs"
                                        icon="trash"
                                        wire:click="deleteComment({{ $comment->id }})"
                                        wire:confirm="{{ __('Delete this comment?') }}"
                                    />
                                @endif
                            </div>
                            <flux:text>{{ $comment->body }}</flux:text>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>
