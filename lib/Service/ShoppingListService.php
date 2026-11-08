<?php
declare(strict_types=1);

namespace OCA\ShoppingList\Service;

use OCP\DB\IDBConnection;
use OCP\IUserSession;

final class ShoppingListService {
    public function __construct(
        private IDBConnection $db,
        private IUserSession $userSession,
    ) {
    }

    private function currentUserId(): string {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new \RuntimeException('Authentication required');
        }
        return $user->getUID();
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
        $now = gmdate('Y-m-d\TH:i:s\Z');
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
            $listId = (int)$this->db->lastInsertId();

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

    public function deleteList(int $listId): void {
        $list = $this->getList($listId);
        if ($list['owner'] !== $this->currentUserId()) {
            throw new \DomainException('Only the list owner can delete this list');
        }

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
}
