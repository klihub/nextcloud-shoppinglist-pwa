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
        } catch (\\InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[NoAdminRequired]
    public function show(int $id): JSONResponse {
        try {
            return new JSONResponse(['list' => $this->service->getList($id)]);
        } catch (\\OutOfBoundsException $e) {
            return new JSONResponse(['error' => 'Shopping list not found'], 404);
        }
    }

    #[NoAdminRequired]
    public function destroy(int $id): JSONResponse {
        try {
            $this->service->deleteList($id);
            return new JSONResponse([], 204);
        } catch (\\OutOfBoundsException $e) {
            return new JSONResponse(['error' => 'Shopping list not found'], 404);
        } catch (\\DomainException $e) {
            return new JSONResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[NoAdminRequired]
    public function members(int $id): JSONResponse {
        try {
            return new JSONResponse(['members' => $this->service->getMembers($id)]);
        } catch (\\OutOfBoundsException $e) {
            return new JSONResponse(['error' => 'Shopping list not found'], 404);
        }
    }

    #[NoAdminRequired]
    public function addMember(int $id, string $userId): JSONResponse {
        try {
            return new JSONResponse(['member' => $this->service->addMember($id, $userId)], 201);
        } catch (\\InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], 400);
        } catch (\\OutOfBoundsException $e) {
            return new JSONResponse(['error' => 'Shopping list not found'], 404);
        } catch (\\DomainException $e) {
            return new JSONResponse(['error' => $e->getMessage()], 409);
        }
    }

    #[NoAdminRequired]
    public function removeMember(int $id, string $userId): JSONResponse {
        try {
            $this->service->removeMember($id, $userId);
            return new JSONResponse([], 204);
        } catch (\\InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], 400);
        } catch (\\OutOfBoundsException $e) {
            return new JSONResponse(['error' => 'List or member not found'], 404);
        } catch (\\DomainException $e) {
            return new JSONResponse(['error' => $e->getMessage()], 403);
        }
    }

    #[NoAdminRequired]
    public function items(int $id): JSONResponse {
        try {
            return new JSONResponse(['items' => $this->service->getItems($id)]);
        } catch (\\OutOfBoundsException $e) {
            return new JSONResponse(['error' => 'Shopping list not found'], 404);
        }
    }

    #[NoAdminRequired]
    public function createItem(int $id, string $name): JSONResponse {
        try {
            return new JSONResponse(['item' => $this->service->createItem($id, $name, $this->request->getParams())], 201);
        } catch (\\InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], 400);
        } catch (\\OutOfBoundsException $e) {
            return new JSONResponse(['error' => 'Shopping list not found'], 404);
        }
    }

    #[NoAdminRequired]
    public function updateItem(int $id, int $itemId): JSONResponse {
        try {
            return new JSONResponse(['item' => $this->service->updateItem($id, $itemId, $this->request->getParams())]);
        } catch (\\InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], 400);
        } catch (\\OutOfBoundsException $e) {
            return new JSONResponse(['error' => 'Shopping list or item not found'], 404);
        }
    }

    #[NoAdminRequired]
    public function deleteItem(int $id, int $itemId): JSONResponse {
        try {
            $this->service->deleteItem($id, $itemId);
            return new JSONResponse([], 204);
        } catch (\\OutOfBoundsException $e) {
            return new JSONResponse(['error' => 'Shopping list or item not found'], 404);
        }
    }
}
