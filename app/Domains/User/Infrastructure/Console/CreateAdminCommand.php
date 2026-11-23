<?php

namespace Domains\User\Infrastructure\Console;

use Domains\User\Application\Commands\CreateBakeryAdmin\CreateBakeryAdminCommand;
use Domains\User\Application\Commands\CreateBakeryAdmin\CreateBakeryAdminHandler;
use Domains\User\Application\EmailAvailabilityService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly CreateBakeryAdminHandler $createAdmin,
        private readonly EmailAvailabilityService $emailAvailability,
    ) {
        parent::__construct();
    }

    protected $signature = 'bakery:create-admin {email : Почта администратора} {--name=Администратор пекарни : Имя администратора}';

    protected $description = 'Создать администратора пекарни с паролем, введённым скрыто в терминале';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->option('name'));
        if (
            filter_var($email, FILTER_VALIDATE_EMAIL) === false ||
            mb_strlen($email) > 255 ||
            $name === '' ||
            mb_strlen($name) > 255
        ) {
            $this->error('Укажите корректную почту и имя длиной не более 255 символов.');

            return self::FAILURE;
        }
        if ($this->emailAvailability->isTaken($email)) {
            $this->error('Аккаунт с такой почтой уже существует.');

            return self::FAILURE;
        }
        if (! $this->input->isInteractive()) {
            $this->error(
                'Для скрытого ввода пароля запустите команду в интерактивном терминале без флага -T.',
            );

            return self::FAILURE;
        }
        $password = $this->secret('Пароль администратора (не менее 10 символов)');
        $confirmation = $this->secret('Повторите пароль');
        if (! is_string($password) || mb_strlen($password) < 10 || $password !== $confirmation) {
            $this->error(
                'Пароль должен содержать не менее 10 символов и совпадать с повторным вводом.',
            );

            return self::FAILURE;
        }

        try {
            $this->createAdmin->handle(new CreateBakeryAdminCommand($email, $name, $password));
        } catch (ValidationException $error) {
            $this->error($error->validator->errors()->first());

            return self::FAILURE;
        }

        $this->info("Администратор {$email} создан. Вход: /login, панель: /admin.");

        return self::SUCCESS;
    }
}
