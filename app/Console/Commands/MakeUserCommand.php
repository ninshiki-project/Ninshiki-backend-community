<?php

namespace App\Console\Commands;

use App\Enum\UserEnum;
use App\Models\Departments;
use App\Models\Designations;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'make:ninshiki-user')]
class MakeUserCommand extends Command
{
    protected $signature = 'make:ninshiki-user
                            {--name= : The name of the user}
                            {--email= : A valid and unique email address}
                            {--role= : A valid role}
                            {--password= : The password for the user (min. 8 characters)}';

    protected $description = 'Create a new Ninshiki Admin User with an Owner Role and Permissions';

    protected array $options;

    /**
     * @return array{'name': string | null, 'email': string | null, 'password': string | null, 'role': string | int | null, 'department': string | int | null, 'designation': string | int | null}
     */
    protected function getUserData(): array
    {
        return [
            'name' => $this->options['name'] ?? text(
                label: 'Name',
                required: true,
            ),

            'email' => $this->options['email'] ?? text(
                label: 'Email address',
                required: true,
                validate: fn (string $email): ?string => match (true) {
                    ! filter_var($email, FILTER_VALIDATE_EMAIL) => 'The email address must be valid.',
                    User::where('email', $email)->exists() => 'A user with this email address already exists',
                    default => null,
                },
            ),

            'department' => $this->options['department'] ?? select(
                label: 'What Department should the user have?',
                options: Departments::pluck('name', 'id')->toArray(),
            ),

            'designation' => $this->options['designation'] ?? select(
                label: 'What Job Title should the user have?',
                options: Designations::pluck('name', 'id')->toArray(),
            ),

            'role' => $this->options['role'] ?? select(
                label: 'What role should the user have?',
                options: Role::pluck('name', 'name')->toArray(),
                validate: fn (string $value) => Role::where('name', '=', $value)->doesntExist()
                    ? 'Invalid Role Supplied'
                    : null
            ),

            'password' => Hash::make($this->options['password'] ?? password(
                label: 'Password',
                required: true,
            )),
        ];
    }

    protected function createUser(): ?User
    {
        $this->options = [
            ...$this->options,
            ...$this->getUserData(),
        ];
        $this->info('logging for creating user');
        try {
            $user = User::create([
                'name' => $this->options['name'],
                'password' => $this->options['password'],
                'email' => $this->options['email'],
                'designation' => $this->options['designation'],
                'department' => $this->options['department'],
                'status' => UserEnum::Active,
            ]);
            $this->callSilently('shield:super-admin', ['--user' => $user->id, '--panel' => 0]);

            return $user;
        } catch (Throwable $th) {
            info($th->getMessage());
        }

        return null;
    }

    protected function sendSuccessMessage(User $user): void
    {
        $loginUrl = config('app.frontend_url', 'http://localhost:3000');

        $this->components->info('Success! '.($user->getAttribute('email') ?? $user->getAttribute('name') ?? 'You')." may now log in at {$loginUrl}");
    }

    protected function prerequisiteDBColumn(): void
    {
        if (Role::count() < 1) {
            $this->call('db:seed --class=TheSeeder --force');
        }
    }

    /**
     * @throws Throwable
     */
    public function handle(): int
    {
        $this->options = $this->options();
        $this->prerequisiteDBColumn();

        $user = $this->createUser();
        if (! $user) {
            $this->components->error('Error! Unable to create user.');

            return static::FAILURE;
        }
        $this->sendSuccessMessage($user);

        return static::SUCCESS;
    }
}
