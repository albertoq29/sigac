<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EstudianteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->esControl();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cedula' => strtoupper(preg_replace('/[\s.]/', '', (string) $this->input('cedula'))),
            'apellidos_nombres' => mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $this->input('apellidos_nombres')))),
            'correo' => $this->filled('correo') ? mb_strtolower(trim((string) $this->input('correo'))) : null,
        ]);
    }

    public function rules(): array
    {
        $estudiante = $this->route('estudiante');

        return [
            'cedula' => [
                'required',
                'regex:/^([VE]-?)?\d{5,10}$/',
                Rule::unique('estudiantes', 'cedula')->ignore($estudiante?->id),
            ],
            'apellidos_nombres' => ['required', 'string', 'max:160'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'sexo' => ['nullable', Rule::in(['F', 'M'])],
            'telefono' => ['nullable', 'string', 'max:80'],
            'correo' => ['nullable', 'email', 'max:120'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'cedula' => 'cédula',
            'apellidos_nombres' => 'apellidos y nombres',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'correo' => 'correo electrónico',
            'telefono' => 'teléfono',
        ];
    }

    public function messages(): array
    {
        return [
            'cedula.regex' => 'La cédula debe tener entre 5 y 10 dígitos (puede empezar con V- o E-).',
            'cedula.unique' => 'Ya existe un estudiante con esa cédula.',
        ];
    }

    /** @return array<string, mixed> */
    public function datosEstudiante(): array
    {
        return $this->safe()->only([
            'cedula', 'apellidos_nombres', 'fecha_nacimiento', 'sexo', 'telefono', 'correo', 'observaciones',
        ]);
    }
}
