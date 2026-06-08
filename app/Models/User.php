<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'firstName',
        'lastName',
        'email',
        'password',
        'bio',
        'profilePicture',
        'agreeTerms',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get the interests for the user.
     */
    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class, 'user_interst')
            ->using(UserInterest::class)
            ->withTimestamps('created', 'updated');
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->using(ConversationParticipant::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function createdCommunities(): HasMany
    {
        return $this->hasMany(Community::class, 'created_by');
    }

    public function communityMemberships(): HasMany
    {
        return $this->hasMany(CommunityMember::class);
    }

    public function communities(): BelongsToMany
    {
        return $this->belongsToMany(Community::class, 'community_members')
            ->using(CommunityMember::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function sentConversationRequests(): HasMany
    {
        return $this->hasMany(ConversationRequest::class, 'sender_id');
    }

    public function receivedConversationRequests(): HasMany
    {
        return $this->hasMany(ConversationRequest::class, 'receiver_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agreeTerms' => 'boolean',
            'password' => 'hashed',
        ];
    }
        /**
     * Return JWT identifier
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

        /**
     * Return custom JWT claims
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
