<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateSuperuser extends Command
{
    protected $signature = 'app:superuser {--email=} {--name=}';

    protected $description = 'Crea (o asciende) un superusuario pidiendo la contraseña por consola';

    public function handle(): int
    {
        $email = $this->option('email') ?: text('Email', required: true, validate: ['email' => 'email']);
        $user = User::firstWhere('email', $email);
        $name = $this->option('name') ?: ($user?->name ?? text('Nombre', required: true));

        $password = password('Contraseña (mín. 8 caracteres)', required: true);
        $confirm = password('Repite la contraseña', required: true);

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirm],
            ['password' => ['confirmed', Password::min(8)]]
        );
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => $password,
            'is_superadmin' => true,
            'active' => true,
        ]);

        $this->info("Superusuario listo: {$user->email}");

        return self::SUCCESS;
    }
}
