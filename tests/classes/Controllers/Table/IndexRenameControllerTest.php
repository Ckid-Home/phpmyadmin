<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Controllers\Table;

use PhpMyAdmin\Config;
use PhpMyAdmin\Controllers\Table\IndexRenameController;
use PhpMyAdmin\Current;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\DbTableExists;
use PhpMyAdmin\Http\Factory\ServerRequestFactory;
use PhpMyAdmin\Index;
use PhpMyAdmin\Table\Indexes;
use PhpMyAdmin\Template;
use PhpMyAdmin\Tests\AbstractTestCase;
use PhpMyAdmin\Tests\Stubs\ResponseRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;

#[CoversClass(IndexRenameController::class)]
class IndexRenameControllerTest extends AbstractTestCase
{
    public function testIndexRenameController(): void
    {
        Current::$database = 'test_db';
        Current::$table = 'test_table';
        $GLOBALS['lang'] = 'en';

        $dummyDbi = $this->createDbiDummy();
        $dummyDbi->addSelectDb('test_db');
        $dummyDbi->addSelectDb('test_db');
        $dummyDbi->addResult('SELECT 1 FROM `test_db`.`test_table` LIMIT 1;', [['1']]);
        $dbi = $this->createDatabaseInterface($dummyDbi);
        DatabaseInterface::$instance = $dbi;

        $template = new Template();
        $expected = $template->render('table/index_rename_form', [
            'index' => new Index(),
            'form_params' => ['db' => 'test_db', 'table' => 'test_table'],
        ]);

        $request = ServerRequestFactory::create()->createServerRequest('GET', 'http://example.com/')
            ->withQueryParams(['db' => 'test_db', 'table' => 'test_table']);

        $response = new ResponseRenderer();
        (new IndexRenameController(
            $response,
            $template,
            $dbi,
            new Indexes($response, $template, $dbi),
            new DbTableExists($dbi),
        ))($request);
        $this->assertSame($expected, $response->getHTMLResult());
    }

    public function testPreviewSqlWithOldStatement(): void
    {
        $indexRegistry = new ReflectionProperty(Index::class, 'registry');
        $indexRegistry->setValue(null, []);

        Config::getInstance()->selectedServer['DisableIS'] = true;

        Current::$database = 'test_db';
        Current::$table = 'test_table';
        $_POST['db'] = 'test_db';
        $_POST['table'] = 'test_table';
        $_POST['old_index'] = 'old_name';
        $_POST['index'] = ['Key_name' => 'new_name'];
        $_POST['do_save_data'] = '1';
        $_POST['preview_sql'] = '1';

        $dbiDummy = $this->createDbiDummy();
        $dbiDummy->addSelectDb('test_db');
        $dbiDummy->addResult('SELECT 1 FROM `test_db`.`test_table` LIMIT 1;', [['1']], ['1']);
        $dbiDummy->addResult(
            'SHOW INDEXES FROM `test_db`.`test_table`',
            [
                ['test_table', '0', 'PRIMARY', 'id', 'BTREE'],
                ['test_table', '1', 'old_name', 'name', 'BTREE'],
            ],
            ['Table', 'Non_unique', 'Key_name', 'Column_name', 'Index_type'],
        );

        $dbi = $this->createDatabaseInterface($dbiDummy);
        $dbi->setVersion(['@@version' => '5.5.0']);
        DatabaseInterface::$instance = $dbi;

        $expected = <<<'HTML'
<div class="preview_sql">
            <code class="sql" dir="ltr"><pre>
ALTER TABLE `test_db`.`test_table` DROP INDEX `old_name`, ADD INDEX `new_name` (`name`) USING BTREE;
</pre></code>
    </div>

HTML;

        $request = ServerRequestFactory::create()->createServerRequest('GET', 'http://example.com/')
            ->withQueryParams(['db' => 'test_db', 'table' => 'test_table'])
            ->withParsedBody([
                'old_index' => 'old_name',
                'index' => ['Key_name' => 'new_name'],
                'do_save_data' => '1',
                'preview_sql' => '1',
            ]);

        $responseRenderer = new ResponseRenderer();
        $template = new Template();
        $controller = new IndexRenameController(
            $responseRenderer,
            $template,
            $dbi,
            new Indexes($responseRenderer, $template, $dbi),
            new DbTableExists($dbi),
        );
        $controller($request);

        self::assertSame(['sql_data' => $expected], $responseRenderer->getJSONResult());

        $dbiDummy->assertAllSelectsConsumed();
        $dbiDummy->assertAllQueriesConsumed();

        $indexRegistry->setValue(null, []);
    }
}
