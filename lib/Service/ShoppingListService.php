<?php
declare(strict_types=1);

namespace OCA\ShoppingList\Service;

use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\IUserSession;

final class ShoppingListService {
    public function __construct(
        private IDBConnection $db,
        private IUserSession $userSession,
        private IUserManager $userManager,
    ) {
    }

    private function currentUserId(): string {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new \RuntimeException('Authentication required');
        }
        return $user->getUID();
    }

    private function now(): string {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    private function touchList(int $listId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('shoppinglist_lists')
            ->set('updated_at', $qb->createNamedParameter($this->now()))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($listId)));
        $qb->executeStatement();
    }

    /** @return list<array<string, mixed>> */
    public function getLists(): array {
        $uid = $this->currentUserId();
        $qb = $this->db->getQueryBuilder();
        $qb->select('l.id', 'l.name', 'l.owner', 'l.created_at', 'l.updated_at')
            ->addSelect($qb->createFunction("COALESCE(m.role, 'owner') AS role"))
            ->from('shoppinglist_lists', 'l')
            ->leftJoin('l', 'shoppinglist_members', 'm', $qb->expr()->andX(
                $qb->expr()->eq('m.list_id', 'l.id'),
                $qb->expr()->eq('m.user_id', $qb->createNamedParameter($uid))
            ))
            ->where($qb->expr()->orX(
                $qb->expr()->eq('l.owner', $qb->createNamedParameter($uid)),
                $qb->expr()->isNotNull('m.user_id')
            ))
            ->orderBy('l.updated_at', 'DESC');

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /** @return array<string, mixed> */
    public function createList(string $name): array {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new \InvalidArgumentException('List name must contain 1–255 characters');
        }

        $uid = $this->currentUserId();
        $now = $this->now();
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('shoppinglist_lists')->values([
                'name' => $qb->createNamedParameter($name),
                'owner' => $qb->createNamedParameter($uid),
                'created_at' => $qb->createNamedParameter($now),
                'updated_at' => $qb->createNamedParameter($now),
            ]);
            $qb->executeStatement();
            $listId = (int)$this->db->lastInsertId('shoppinglist_lists');

            $memberQb = $this->db->getQueryBuilder();
            $memberQb->insert('shoppinglist_members')->values([
                'list_id' => $memberQb->createNamedParameter($listId),
                'user_id' => $memberQb->createNamedParameter($uid),
                'role' => $memberQb->createNamedParameter('owner'),
                'created_at' => $memberQb->createNamedParameter($now),
            ]);
            $memberQb->executeStatement();
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'id' => $listId,
            'name' => $name,
            'owner' => $uid,
            'role' => 'owner',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** @return array<string, mixed> */
    public function getList(int $listId): array {
        $uid = $this->currentUserId();
        $qb = $this->db->getQueryBuilder();
        $qb->select('l.id', 'l.name', 'l.owner', 'l.created_at', 'l.updated_at')
            ->addSelect($qb->createFunction("COALESCE(m.role, 'owner') AS role"))
            ->from('shoppinglist_lists', 'l')
            ->leftJoin('l', 'shoppinglist_members', 'm', $qb->expr()->andX(
                $qb->expr()->eq('m.list_id', 'l.id'),
                $qb->expr()->eq('m.user_id', $qb->createNamedParameter($uid))
            ))
            ->where($qb->expr()->eq('l.id', $qb->createNamedParameter($listId)))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('l.owner', $qb->createNamedParameter($uid)),
                $qb->expr()->isNotNull('m.user_id')
            ));

        $list = $qb->executeQuery()->fetchAssociative();
        if ($list === false) {
            throw new \OutOfBoundsException('Shopping list not found');
        }
        return $list;
    }

    private function requireOwner(int $listId): array {
        $list = $this->getList($listId);
        if ($list['owner'] !== $this->currentUserId()) {
            throw new \DomainException('Only the list owner can manage sharing or delete this list');
        }
        return $list;
    }

    public function deleteList(int $listId): void {
        $this->requireOwner($listId);
        $this->db->beginTransaction();
        try {
            foreach (['shoppinglist_items', 'shoppinglist_members'] as $table) {
                $qb = $this->db->getQueryBuilder();
                $qb->delete($table)
                    ->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId)));
                $qb->executeStatement();
            }
            $qb = $this->db->getQueryBuilder();
            $qb->delete('shoppinglist_lists')
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($listId)));
            $qb->executeStatement();
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function getMembers(int $listId): array {
        $this->getList($listId);
        $qb = $this->db->getQueryBuilder();
        $qb->select('user_id', 'role', 'created_at')
            ->from('shoppinglist_members')
            ->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId)))
            ->orderBy('role', 'ASC')
            ->addOrderBy('user_id', 'ASC');
        $members = $qb->executeQuery()->fetchAllAssociative();
        foreach ($members as &$member) {
            $member['display_name'] = $this->userManager->getDisplayName((string)$member['user_id']) ?? $member['user_id'];
        }
        unset($member);
        return $members;
    }

    /** @return array<string, mixed> */
    public function addMember(int $listId, string $userId): array {
        $this->requireOwner($listId);
        $userId = trim($userId);
        if ($userId === '' || !$this->userManager->userExists($userId)) {
            throw new \InvalidArgumentException('No Nextcloud user exists with that user ID');
        }
        $list = $this->getList($listId);
        if ($userId === $list['owner']) {
            throw new \InvalidArgumentException('The list owner is already a member');
        }

        $check = $this->db->getQueryBuilder();
        $check->select('user_id')
            ->from('shoppinglist_members')
            ->where($check->expr()->eq('list_id', $check->createNamedParameter($listId)))
            ->andWhere($check->expr()->eq('user_id', $check->createNamedParameter($userId)));
        if ($check->executeQuery()->fetchOne() !== false) {
            throw new \DomainException('That user is already a member of this list');
        }

        $qb = $this->db->getQueryBuilder();
        $qb->insert('shoppinglist_members')->values([
            'list_id' => $qb->createNamedParameter($listId),
            'user_id' => $qb->createNamedParameter($userId),
            'role' => $qb->createNamedParameter('member'),
            'created_at' => $qb->createNamedParameter($this->now()),
        ]);
        $qb->executeStatement();
        $this->touchList($listId);

        return [
            'user_id' => $userId,
            'display_name' => $this->userManager->getDisplayName($userId) ?? $userId,
            'role' => 'member',
        ];
    }

    public function removeMember(int $listId, string $userId): void {
        $list = $this->requireOwner($listId);
        if ($userId === $list['owner']) {
            throw new \InvalidArgumentException('The list owner cannot be removed');
        }
        $qb = $this->db->getQueryBuilder();
        $qb->delete('shoppinglist_members')
            ->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId)))
            ->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
        if ($qb->executeStatement() === 0) {
            throw new \OutOfBoundsException('List member not found');
        }
        $this->touchList($listId);
    }

    /** @return list<array<string, mixed>> */
    public function getItems(int $listId): array {
        $this->getList($listId);
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('shoppinglist_items')
            ->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId)))
            ->orderBy('checked', 'ASC')
            ->addOrderBy('sort_order', 'ASC')
            ->addOrderBy('id', 'ASC');
        return $qb->executeQuery()->fetchAllAssociative();
    }

    /** @return array<string, mixed> */
    public function createItem(int $listId, string $name, array $data = []): array {
        $this->getList($listId);
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 255) {
            throw new \InvalidArgumentException('Item name must contain 1–255 characters');
        }
        $quantity = isset($data['quantity']) ? trim((string)$data['quantity']) : '1';
        $note = isset($data['note']) ? trim((string)$data['note']) : null;
        $category = isset($data['category']) ? trim((string)$data['category']) : null;
        if (mb_strlen($quantity) > 64 || ($category !== null && mb_strlen($category) > 64)) {
            throw new \InvalidArgumentException('Quantity and category must be at most 64 characters');
        }

        $uid = $this->currentUserId();
        $now = $this->now();
        $qb = $this->db->getQueryBuilder();
        $qb->insert('shoppinglist_items')->values([
            'list_id' => $qb->createNamedParameter($listId),
            'name' => $qb->createNamedParameter($name),
            'quantity' => $qb->createNamedParameter($quantity === '' ? null : $quantity),
            'note' => $qb->createNamedParameter($note === '' ? null : $note),
            'category' => $qb->createNamedParameter($category === '' ? null : $category),
            'checked' => $qb->createNamedParameter(false, \PDO::PARAM_BOOL),
            'sort_order' => $qb->createNamedParameter((int)($data['sort_order'] ?? 0)),
            'created_by' => $qb->createNamedParameter($uid),
            'created_at' => $qb->createNamedParameter($now),
            'updated_at' => $qb->createNamedParameter($now),
        ]);
        $qb->executeStatement();
        $itemId = (int)$this->db->lastInsertId('shoppinglist_items');
        $this->touchList($listId);
        return $this->getItem($listId, $itemId);
    }

    /** @return array<string, mixed> */
    private function getItem(int $listId, int $itemId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('shoppinglist_items')
            ->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId)))
            ->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($itemId)));
        $item = $qb->executeQuery()->fetchAssociative();
        if ($item === false) {
            throw new \OutOfBoundsException('Shopping list item not found');
        }
        return $item;
    }

    /** @return array<string, mixed> */
    public function updateItem(int $listId, int $itemId, array $data): array {
        $this->getList($listId);
        $this->getItem($listId, $itemId);
        $allowed = ['name', 'quantity', 'note', 'category', 'checked', 'sort_order'];
        $updates = array_intersect_key($data, array_flip($allowed));
        if ($updates === []) {
            throw new \InvalidArgumentException('No item fields were supplied');
        }

        $qb = $this->db->getQueryBuilder();
        $qb->update('shoppinglist_items');
        foreach ($updates as $field => $value) {
            if ($field === 'name') {
                $value = trim((string)$value);
                if ($value === '' || mb_strlen($value) > 255) {
                    throw new \InvalidArgumentException('Item name must contain 1–255 characters');
                }
            } elseif ($field === 'quantity') {
                $value = $value === null ? null : trim((string)$value);
                if ($value !== null && mb_strlen($value) > 64) {
                    throw new \InvalidArgumentException('Quantity must be at most 64 characters');
                }
                if ($value === '') {
                    $value = null;
                }
            } elseif ($field === 'note' || $field === 'category') {
                $value = $value === null ? null : trim((string)$value);
                if ($field === 'category' && $value !== null && mb_strlen($value) > 64) {
                    throw new \InvalidArgumentException('Category must be at most 64 characters');
                }
                if ($value === '') {
                    $value = null;
                }
            } elseif ($field === 'checked') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($value === null) {
                    throw new \InvalidArgumentException('Checked must be a boolean');
                }
            } elseif ($field === 'sort_order') {
                if (!is_numeric($value)) {
                    throw new \InvalidArgumentException('Sort order must be an integer');
                }
                $value = (int)$value;
            }
            $parameter = $field === 'checked'
                ? $qb->createNamedParameter($value, \PDO::PARAM_BOOL)
                : $qb->createNamedParameter($value);
            $qb->set($field, $parameter);
        }
        $qb->set('updated_at', $qb->createNamedParameter($this->now()))
            ->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId)))
            ->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($itemId)));
        $qb->executeStatement();
        $this->touchList($listId);
        return $this->getItem($listId, $itemId);
    }

    public function deleteItem(int $listId, int $itemId): void {
        $this->getList($listId);
        $qb = $this->db->getQueryBuilder();
        $qb->delete('shoppinglist_items')
            ->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId)))
            ->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($itemId)));
        if ($qb->executeStatement() === 0) {
            throw new \OutOfBoundsException('Shopping list item not found');
        }
        $this->touchList($listId);
    }
}
