<?php
declare(strict_types=1);

namespace OCA\ShoppingList\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version010000Date20261108000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if (!$schema->hasTable('shoppinglist_lists')) {
            $table = $schema->createTable('shoppinglist_lists');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
            $table->addColumn('name', Types::STRING, ['length' => 255]);
            $table->addColumn('owner', Types::STRING, ['length' => 64]);
            $table->addColumn('created_at', Types::STRING, ['length' => 32]);
            $table->addColumn('updated_at', Types::STRING, ['length' => 32]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['owner'], 'shoppinglist_lists_owner');
        }

        if (!$schema->hasTable('shoppinglist_members')) {
            $table = $schema->createTable('shoppinglist_members');
            $table->addColumn('list_id', Types::BIGINT, ['unsigned' => true]);
            $table->addColumn('user_id', Types::STRING, ['length' => 64]);
            $table->addColumn('role', Types::STRING, ['length' => 16]);
            $table->addColumn('created_at', Types::STRING, ['length' => 32]);
            $table->setPrimaryKey(['list_id', 'user_id']);
            $table->addIndex(['user_id'], 'shoppinglist_members_user');
        }

        if (!$schema->hasTable('shoppinglist_items')) {
            $table = $schema->createTable('shoppinglist_items');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
            $table->addColumn('list_id', Types::BIGINT, ['unsigned' => true]);
            $table->addColumn('name', Types::STRING, ['length' => 255]);
            $table->addColumn('quantity', Types::STRING, ['length' => 64, 'notnull' => false]);
            $table->addColumn('note', Types::TEXT, ['notnull' => false]);
            $table->addColumn('category', Types::STRING, ['length' => 64, 'notnull' => false]);
            $table->addColumn('checked', Types::BOOLEAN, ['default' => false]);
            $table->addColumn('sort_order', Types::INTEGER, ['default' => 0]);
            $table->addColumn('created_by', Types::STRING, ['length' => 64]);
            $table->addColumn('created_at', Types::STRING, ['length' => 32]);
            $table->addColumn('updated_at', Types::STRING, ['length' => 32]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['list_id'], 'shoppinglist_items_list');
        }

        return $schema;
    }
}
