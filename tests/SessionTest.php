<?php

/**
 * This file is part of Laucov's Web Framework project.
 * 
 * Copyright 2024 Laucov Serviços de Tecnologia da Informação Ltda.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * @package web-framework
 * 
 * @author Rafael Covaleski Pereira <rafael.covaleski@laucov.com>
 * 
 * @license <http://www.apache.org/licenses/LICENSE-2.0> Apache License 2.0
 * 
 * @copyright © 2024 Laucov Serviços de Tecnologia da Informação Ltda.
 */

namespace Tests\Session;

use Laucov\Sessions\Handlers\FileSessionHandler;
use Laucov\Sessions\Handlers\Interfaces\SessionHandlerInterface;
use Laucov\Sessions\Session;
use Laucov\Sessions\Status\SessionOpening;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Laucov\Sessions\Session
 */
class SessionTest extends TestCase
{
    /**
     * Session handler.
     */
    protected SessionHandlerInterface $handler;
    
    /**
     * Session instance.
     */
    protected Session $session;

    /**
     * @covers ::__construct
     * @covers ::close
     * @covers ::commit
     * @covers ::destroy
     * @covers ::get
     * @covers ::open
     * @covers ::regenerate
     * @covers ::set
     * @covers ::unset
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::__construct
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::close
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::create
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::destroy
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::open
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::read
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::regenerate
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::write
     */
    public function testCanManipulateSession(): void
    {
        // Create session.
        $this->assertObjectHasProperty('id', $this->session);
        $id = $this->session->id;

        // Open, write and commit.
        $this->session
            ->open()
            ->set('foo', 'bar')
            ->set('bar.baz', 'foo')
            ->commit();
        
        // Check values.
        $this->handler->open($id, false);
        $this->assertSame(
            'a:2:{s:3:"foo";s:3:"bar";s:3:"bar";a:1:{s:3:"baz";s:3:"foo";}}',
            $this->handler->read($id),
        );

        // Set new values.
        $data = 'a:2:{s:2:"id";i:2;s:4:"data";a:1:{s:4:"name";s:4:"John";}}';
        $this->handler->write($id, $data);
        $this->handler->close($id);

        // Open, read, write and commit (manually close).
        $this->session->open(true);
        $this->assertSame(2, $this->session->get('id'));
        $this->assertSame('John', $this->session->get('data.name'));
        $this->assertNull($this->session->get('foo'));
        $this->assertSame('nothing', $this->session->get('foo.bar', 'nothing'));
        $this->session
            ->set('data.name', 'Harry')
            ->commit(false);
        
        // Check committed values.
        $this->assertSame(
            'a:2:{s:2:"id";i:2;s:4:"data";a:1:{s:4:"name";s:5:"Harry";}}',
            $this->handler->read($id),
        );

        // Close.
        $this->session
            ->close()
            ->open();
        
        // Regenerate.
        $this->session->regenerate(false);
        $this->assertNotSame($id, $this->session->id);
        $id = $this->session->id;
        $this->session->regenerate(true);
        $this->assertSame(
            SessionOpening::NOT_FOUND,
            $this->handler->open($id, false),
        );

        // Unset values.
        $this->session->unset();
        $this->assertNull($this->session->get('id'));
        $this->assertNull($this->session->get('data.name'));
        $this->assertNull($this->session->get('data'));
        $this->session->commit(false);
        $this->assertSame('', $this->handler->read($this->session->id));

        // Destroy session.
        $id = $this->session->id;
        $this->session->destroy();
        $this->assertSame('', $this->session->id);
        $this->assertSame(
            SessionOpening::NOT_FOUND,
            $this->handler->open($id, false),
        );
    }

    /**
     * @covers ::open
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::__construct
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::close
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::create
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::open
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::read
     * @uses Laucov\Sessions\Session::__construct
     * @uses Laucov\Sessions\Session::close
     */
    public function testCannotOpenTwice(): void
    {
        $this->session->open();
        $this->session->close();
        $this->session->open();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Could not open the session: Session already open.',
        );

        $this->session->open();
    }

    /**
     * @covers ::close
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::__construct
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::close
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::create
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::open
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::read
     * @uses Laucov\Sessions\Session::__construct
     * @uses Laucov\Sessions\Session::open
     */
    public function testMustOpenBeforeClosing(): void
    {
        $this->session->open();
        $this->session->close();
        $this->expectException(\RuntimeException::class);
        $this->session->close();
    }

    /**
     * @covers ::destroy
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::__construct
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::close
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::create
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::destroy
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::open
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::read
     * @uses Laucov\Sessions\Session::__construct
     * @uses Laucov\Sessions\Session::open
     * @uses Laucov\Sessions\Session::close
     */
    public function testMustOpenBeforeDestroying(): void
    {
        $this->session->open();
        $this->session->close();
        $this->expectException(\RuntimeException::class);
        $this->session->destroy();
    }
    
    /**
     * @covers ::open
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::__construct
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::close
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::create
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::open
     * @uses Laucov\Sessions\Handlers\FileSessionHandler::read
     * @uses Laucov\Sessions\Session::__construct
     * @uses Laucov\Sessions\Session::close
     */
    public function testSessionIdMustExist(): void
    {
        $session = new Session($this->handler, 'invalid_id');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Could not open the session: Session not found.',
        );

        $session->open();
    }

    protected function setUp(): void
    {
        $this->handler = new FileSessionHandler(__DIR__ . '/session-files');
        $id = $this->handler->create();
        $this->session = new Session($this->handler, $id);
    }

    protected function tearDown(): void
    {
        $items = array_diff(scandir(__DIR__ . '/session-files'), ['.', '..']);
        foreach ($items as $item) {
            $filename = __DIR__ . "/session-files/{$item}";
            if (!is_dir($filename)) {
                unlink($filename);
            }
        }
    }
}
