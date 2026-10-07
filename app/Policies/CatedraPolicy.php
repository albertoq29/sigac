<?php

namespace App\Policies;

use App\Models\Catedra;
use App\Models\User;

class CatedraPolicy
{
    /** Ver la cátedra, su lista y sus reportes. */
    public function view(User $user, Catedra $catedra): bool
    {
        return $user->esControl() || $catedra->profesor_id === $user->id;
    }

    /** Pasar asistencia y cargar notas. */
    public function registrar(User $user, Catedra $catedra): bool
    {
        return $user->esControl() || $catedra->profesor_id === $user->id;
    }

    /** Crear, editar, reabrir cierres: solo Control de Estudios. */
    public function gestionar(User $user, ?Catedra $catedra = null): bool
    {
        return $user->esControl();
    }
}
