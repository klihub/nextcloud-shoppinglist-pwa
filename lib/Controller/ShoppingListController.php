<?php
declare(strict_types=1);

namespace OCA\ShoppingList\Controller;

use OCA\ShoppingList\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

final class ShoppingListController extends Controller {
    public function __construct(IRequest $request) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Initial API placeholder. List retrieval will be implemented with
     * membership-aware queries before this endpoint is exposed as usable.
     */
    public function index(): JSONResponse {
        return new JSONResponse(['data' => [], 'status' => 'not_implemented'], 501);
    }
}
