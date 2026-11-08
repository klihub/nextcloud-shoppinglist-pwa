<?php
declare(strict_types=1);

namespace OCA\ShoppingList\Controller;

use OCA\ShoppingList\AppInfo\Application;
use OCA\ShoppingList\Service\ShoppingListService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;

final class ShoppingListController extends Controller {
    public function __construct(
        IRequest $request,
        private ShoppingListService $service,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    public function index(): JSONResponse {
        return new JSONResponse(['lists' => $this->service->getLists()]);
    }

    #[NoAdminRequired]
    public function create(string $name): JSONResponse {
        try {
            return new JSONResponse(['list' => $this->service->createList($name)], 201);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[NoAdminRequired]
    public function show(int $id): JSONResponse {
        try {
            return new JSONResponse(['list' => $this->service->getList($id)]);
        } catch (\OutOfBoundsException $e) {
            return new JSONResponse(['error' => 'Shopping list not found'], 404);
        }
    }

    #[NoAdminRequired]
    public function destroy(int $id): JSONResponse {
        try {
            $this->service->deleteList($id);
            return new JSONResponse([], 204);
        } catch (\OutOfBoundsException $e) {
            return new JSONResponse(['error' => 'Shopping list not found'], 404);
        } catch (\DomainException $e) {
            return new JSONResponse(['error' => $e->getMessage()], 403);
        }
    }
}
