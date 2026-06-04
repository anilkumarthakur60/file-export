<?php

declare(strict_types=1);

namespace Anil\FileExport\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/**
 * Minimal Eloquent model for exercising query-based exports and notifications.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $active
 */
class User extends Model
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'active' => 'boolean',
    ];
}
