<?php

namespace PUXT\Tests;

use PHPUnit\Framework\TestCase;
use PUXT\Config;
use PUXT\Config\Exception\ExceptionInterface;
use PUXT\Config\Exception\InvalidArgumentException;
use PUXT\Config\Exception\RuntimeException;

final class ConfigTest extends TestCase
{
    public function testNestedPropertyAndArrayAccessShareTheSameObject(): void
    {
        $config = new Config(['VX' => ['enabled' => false]], true);
        $vxConfig = $config;
        $section = $vxConfig->VX;
        self::assertInstanceOf(Config::class, $section);
        self::assertSame($section, $config['VX']);

        $vxConfig['VX']['enabled'] = true;
        self::assertTrue($config->VX->enabled);
        $section->customer = '客戶';
        self::assertSame('客戶', $config['VX']['customer']);

        $config['VX']['table'] = ['User' => 'customers'];
        self::assertInstanceOf(Config::class, $config->VX->table);
        self::assertSame(['User' => 'customers'], iterator_to_array($config->VX->table));
    }

    public function testMissingKeysDefaultsAndNullValues(): void
    {
        $config = new Config(['null' => null, 'false' => false, 'empty' => '']);
        self::assertNull($config->missing);
        self::assertNull($config['missing']);
        self::assertSame('fallback', $config->get('missing', 'fallback'));
        self::assertNull($config->get('null', 'fallback'));
        self::assertFalse(isset($config->null));
        self::assertFalse(isset($config['missing']));
        self::assertTrue(isset($config['false']));
        self::assertFalse($config->get('false', true));
        self::assertSame('', $config->get('empty', 'fallback'));
    }

    public function testWritesAreReadOnlyByDefault(): void
    {
        $config = new Config(['VX' => ['enabled' => true]]);
        self::assertTrue($config->isReadOnly());
        self::assertTrue($config->VX->isReadOnly());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Config is read only');
        $config['enabled'] = false;
    }

    public function testReadOnlyExceptionImplementsMarkerInterface(): void
    {
        $config = new Config([]);
        try {
            $config->value = 1;
            self::fail('Read-only writes must throw.');
        } catch (RuntimeException $exception) {
            self::assertInstanceOf(\RuntimeException::class, $exception);
            self::assertInstanceOf(ExceptionInterface::class, $exception);
        }
    }

    public function testReadOnlyUnsetThrows(): void
    {
        $config = new Config(['value' => 1]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config is read only');
        unset($config['value']);
    }

    public function testSetReadOnlyAlsoLocksPreviouslyObtainedChildren(): void
    {
        $config = new Config(['VX' => ['table' => ['User' => 'users']]], true);
        $table = $config->VX->table;
        self::assertFalse($table->isReadOnly());
        $config->setReadOnly();
        self::assertTrue($config->VX->isReadOnly());
        self::assertTrue($table->isReadOnly());
        $this->expectException(RuntimeException::class);
        $table['User'] = 'changed';
    }

    public function testMergePreservesNestedKeysAndAppendsNumericKeys(): void
    {
        $config = new Config([
            'VX' => ['jwt' => ['secret' => 'old', 'algorithm' => 'HS256']],
            'plugins' => ['first.php'],
            'replace' => ['old' => true],
            'scalar' => 'before',
        ], true);
        $override = new Config([
            'VX' => ['jwt' => ['secret' => 'new']],
            'plugins' => ['second.php'],
            'replace' => 'scalar',
            'scalar' => ['nested' => 'after'],
            'new' => ['enabled' => true],
        ]);
        self::assertSame($config, $config->merge($override));
        self::assertSame([
            'VX' => ['jwt' => ['secret' => 'new', 'algorithm' => 'HS256']],
            'plugins' => ['first.php', 'second.php'],
            'replace' => 'scalar',
            'scalar' => ['nested' => 'after'],
            'new' => ['enabled' => true],
        ], $config->toArray());
        $config->new->enabled = false;
        self::assertTrue($override->new->enabled);
    }

    public function testToArrayPreservesNestedTypesAndKeys(): void
    {
        $object = new \stdClass();
        $input = ['VX' => ['list' => [2 => 'two', 5 => 'five'], 'empty' => []], 'null' => null, 'object' => $object];
        $config = new Config($input, true);
        self::assertSame($input, $config->toArray());
        $array = $config->toArray();
        $array['VX']['list'][2] = 'changed';
        self::assertSame('two', $config['VX']['list'][2]);
        self::assertSame($object, $config->object);
    }

    public function testCountIterationAppendAndUnset(): void
    {
        $config = new Config(['first' => 1, 'second' => 2, 'third' => 3], true);
        $visited = [];
        foreach ($config as $key => $value) {
            $visited[$key] = $value;
            if ($key === 'second') {
                unset($config[$key]);
            }
        }
        self::assertSame(['first' => 1, 'second' => 2, 'third' => 3], $visited);
        self::assertCount(2, $config);
        unset($config->missing);
        $config[] = 4;
        self::assertSame(['first' => 1, 'third' => 3, 0 => 4], iterator_to_array($config));
        self::assertFalse(isset($config->second));
    }

    public function testCloneCopiesNestedConfigObjects(): void
    {
        $config = new Config(['VX' => ['table' => ['User' => 'users']]], true);
        $copy = clone $config;
        self::assertNotSame($config->VX, $copy->VX);
        self::assertNotSame($config->VX->table, $copy->VX->table);
        $copy->VX->table->User = 'customers';
        self::assertSame('users', $config->VX->table->User);
        self::assertSame('customers', $copy['VX']['table']['User']);
    }

    public function testVxStylePhpYamlAndDatabaseOverrides(): void
    {
        $puxtConfig = new Config(['VX' => ['site' => 'PHP', 'table' => ['User' => 'users']]], true);
        $vxConfig = $puxtConfig;
        $vxConfig['VX']['authentication_lock'] = true;

        // VX assigns parsed YAML values, followed by database values, into the same object.
        foreach (['site' => 'YAML', 'locale' => 'zh-HK'] as $key => $value) {
            $vxConfig['VX'][$key] = $value;
        }
        foreach (['site' => 'Database'] as $key => $value) {
            $vxConfig['VX'][$key] = $value;
        }
        self::assertSame('Database', $puxtConfig->VX->site);
        self::assertSame('zh-HK', $puxtConfig['VX']['locale']);
        self::assertTrue($puxtConfig->VX->authentication_lock);
        self::assertSame(['User' => 'users'], iterator_to_array($puxtConfig->VX->table));
    }
}
