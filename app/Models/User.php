<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Quién puede entrar al panel.
     *
     * Filament exige esto en producción: si el modelo no implementa
     * FilamentUser, su middleware responde 403 a TODO el mundo. Es una red
     * de seguridad deliberada, para que nadie publique un panel abierto por
     * olvidar configurar el acceso.
     *
     * La regla es tener un rol. Un usuario sin rol no tiene ni un permiso,
     * así que entraría a un panel sin un solo módulo visible y parecería que
     * el sistema está roto; es más claro negarle el paso y que alguien le
     * asigne su rol.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->roles()->exists();
    }
    
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
        ];
    }
}
