<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'email_verified_at', 'profile_avatar_social_account_id', 'password', 'is_instance_admin', 'two_factor_secret', 'two_factor_confirmed_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_instance_admin' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function profileAvatarSource(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'profile_avatar_social_account_id');
    }

    public function validConnectedIdentities(): HasMany
    {
        $relation = $this->socialAccounts()->where('email_verified', true);

        if (! $this->hasVerifiedEmail()) {
            return $relation->whereRaw('1 = 0');
        }

        return $relation->where('email', $this->email);
    }

    public function hasPassword(): bool
    {
        return filled($this->password);
    }

    public function isValidConnectedIdentity(SocialAccount $account): bool
    {
        return $this->hasVerifiedEmail()
            && $account->user_id === $this->id
            && $account->email_verified
            && hash_equals((string) $this->email, (string) $account->email);
    }

    public function validSignInMethodCount(?int $excludingSocialAccountId = null): int
    {
        $count = $this->hasPassword() ? 1 : 0;

        $query = $this->validConnectedIdentities();

        if ($excludingSocialAccountId) {
            $query->whereKeyNot($excludingSocialAccountId);
        }

        return $count + $query->count();
    }

    public function profileAvatarUrl(): ?string
    {
        $source = $this->profileAvatarSource;

        if (! $source || ! $source->avatar_url || ! $this->isValidConnectedIdentity($source)) {
            return null;
        }

        return $source->avatar_url;
    }

    public function refreshProfileAvatarSource(): void
    {
        $source = $this->profileAvatarSource;

        if ($source && $source->avatar_url && $this->isValidConnectedIdentity($source)) {
            return;
        }

        $replacement = $this->validConnectedIdentities()
            ->whereNotNull('avatar_url')
            ->oldest()
            ->first();

        $this->forceFill([
            'profile_avatar_social_account_id' => $replacement?->id,
        ])->save();
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }
}
