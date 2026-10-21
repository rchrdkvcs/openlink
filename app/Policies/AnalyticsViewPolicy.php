<?php

namespace App\Policies;

use App\Models\AnalyticsView;
use App\Models\User;

class AnalyticsViewPolicy
{
    public function view(User $user, AnalyticsView $view): bool
    {
        return $view->user_id === $user->id && $user->roleIn($view->workspace) !== null;
    }

    public function update(User $user, AnalyticsView $view): bool
    {
        return $this->view($user, $view);
    }

    public function delete(User $user, AnalyticsView $view): bool
    {
        return $this->view($user, $view);
    }
}
