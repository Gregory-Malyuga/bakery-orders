<?php

namespace Domains\User\Presentation\Http\Controllers;

use Domains\User\Application\Commands\Logout\LogoutCommand;
use Domains\User\Application\Data\LogoutData;
use Domains\User\Domain\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Shared\Bus\CommandBusInterface;

class LogoutController extends Controller
{
    public function __construct(private readonly CommandBusInterface $commands) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tokenId = $user->currentAccessToken()->getKey();
        assert(is_int($tokenId));
        $this->commands->dispatch(new LogoutCommand($tokenId));

        return response()->json(new LogoutData('Выход выполнен.'));
    }
}
