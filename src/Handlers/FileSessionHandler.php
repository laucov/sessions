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

namespace Laucov\Sessions\Handlers;

use Laucov\Sessions\Handlers\Interfaces\SessionHandlerInterface;
use Laucov\Sessions\Status\SessionClosing;
use Laucov\Sessions\Status\SessionDestruction;
use Laucov\Sessions\Status\SessionOpening;

/**
 * Allows reading and writing sessions using files.
 */
class FileSessionHandler implements SessionHandlerInterface
{
    /**
     * Base directory.
     */
    protected string $directory;

    /**
     * Open sessions.
     * 
     * @var array<string, resource>
     */
    protected array $sessions = [];

    /**
     * Create the session handler instance.
     */
    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '\\/') . DIRECTORY_SEPARATOR;
    }

    /**
     * Close the session with the given ID.
     */
    public function close(string $id): SessionClosing
    {
        // Check if the session is open.
        if (!array_key_exists($id, $this->sessions)) {
            return SessionClosing::NOT_OPEN;
        }

        // Try to close the file.
        try {
            $success = fclose($this->sessions[$id]);
            // @codeCoverageIgnoreStart
        } catch (\Throwable $t) {
            $success = false;
            $error = $t->getMessage();
        } finally {
            if (!$success) {
                $msg = 'Could not close the session file: '
                    . ($error ?? 'fclose() failure.');
                throw new \RuntimeException($msg);
            }
            // @codeCoverageIgnoreEnd
        }

        // Unregister ID.
        unset($this->sessions[$id]);

        return SessionClosing::CLOSED;
    }

    /**
     * Create a new session.
     */
    public function create(): string
    {
        // Create new ID.
        $id = uniqid();
        
        // Create the session file without opening it.
        try {
            $success = touch($this->directory . $id);
            // @codeCoverageIgnoreStart
        } catch (\Throwable $t) {
            $success = false;
            $error = $t->getMessage();
        } finally {
            if (!$success) {
                $msg = 'Could not create the new session file: '
                    . ($error ?? 'touch() failure.');
                throw new \RuntimeException($msg);
            }
            // @codeCoverageIgnoreEnd
        }
        
        return $id;
    }

    /**
     * Remove all data and eliminate the session with the given ID.
     */
    public function destroy(string $id): SessionDestruction
    {
        // Check if the session is open.
        if (!array_key_exists($id, $this->sessions)) {
            return SessionDestruction::NOT_OPEN;
        }

        // Close the session.
        $this->close($id);

        // Remove the session file.
        try {
            $success = unlink($this->directory . $id);
            // @codeCoverageIgnoreStart
        } catch (\Throwable $t) {
            $success = false;
            $error = $t->getMessage();
        } finally {
            if (!$success) {
                $msg = 'Could not delete the session file: '
                    . ($error ?? 'unlink() failure.');
                throw new \RuntimeException($msg);
            }
            // @codeCoverageIgnoreEnd
        }

        return SessionDestruction::DESTROYED;
    }

    /**
     * Open the session with the given ID.
     */
    public function open(string $id, bool $readonly = false): SessionOpening
    {
        // Check if session is already open.
        if (array_key_exists($id, $this->sessions)) {
            return SessionOpening::ALREADY_OPEN;
        }

        // Check if session exists.
        if (!file_exists($this->directory . $id)) {
            return SessionOpening::NOT_FOUND;
        }

        // Open session.
        try {
            $resource = fopen($this->directory . $id, 'c+');
            $lock = $readonly ? LOCK_SH : LOCK_EX;
            $flock = $resource && flock($resource, $lock);
            $success = $flock;
            // @codeCoverageIgnoreStart
        } catch (\Throwable $t) {
            $success = false;
            $error = $t->getMessage();
        } finally {
            if (!$success) {
                $msg = 'Could not open the session file: ';
                $msg .= $error ?? match (true) {
                    !$resource => 'fopen() failure.',
                    !$flock => 'flock() failure.',
                };
                throw new \RuntimeException($msg);
            }
            // @codeCoverageIgnoreEnd
        }

        // Register open session.
        $this->sessions[$id] = $resource;

        return SessionOpening::OPEN;
    }

    /**
     * Read all data from the session with the given ID.
     */
    public function read(string $id): string
    {
        // Check if the session exists.
        if (!array_key_exists($id, $this->sessions)) {
            $msg = 'Cannot read a session that is not open.';
            throw new \RuntimeException($msg);
        }

        // Get the session.
        $resource = $this->sessions[$id];

        // Read contents.
        try {
            $result = stream_get_contents($resource, null, 0);
            // @codeCoverageIgnoreStart
        } catch (\Throwable $t) {
            $result = false;
            $error = $t->getMessage();
        } finally {
            if (!is_string($result)) {
                $msg = 'Could not read the session file: ';
                $msg .= $error ?? 'stream_get_contents() failure.';
                throw new \RuntimeException($msg);
            }
            // @codeCoverageIgnoreEnd
        }

        return $result;
    }

    /**
     * Regenerate a session.
     */
    public function regenerate(string $id, bool $delete_old_session): string
    {
        // Get old session data.
        $data = $this->read($id);

        // Create ID.
        $new_id = uniqid();

        // Create file and copy the old session content.
        try {
            $resource = fopen($this->directory . $new_id, 'c+');
            $flock = $resource && flock($resource, LOCK_EX);
            $ftruncate = $flock && ftruncate($resource, 0);
            $fwrite = $ftruncate && fwrite($resource, $data);
            $success = $fwrite;
            // @codeCoverageIgnoreStart
        } catch (\Throwable $t) {
            $success = false;
            $error = $t->getMessage();
        } finally {
            if (!$success) {
                $msg = 'Could not create the session file when regenerating: ';
                $msg .= $error ?? match (true) {
                    !$resource => 'fopen() failure.',
                    !$flock => 'flock() failure.',
                    !$ftruncate => 'ftruncate() failure.',
                    !$fwrite => 'fwrite() failure.',
                };
                throw new \RuntimeException($msg);
            }
            // @codeCoverageIgnoreEnd
        }

        // Register new session.
        $this->sessions[$new_id] = $resource;

        // Close old session.
        fclose($this->sessions[$id]);
        unset($this->sessions[$id]);

        // Delete old session.
        if ($delete_old_session) {
            unlink($this->directory . $id);
        }

        return $new_id;
    }

    /**
     * Check if a session ID is valid.
     */
    public function validate(string $id): bool
    {
        return preg_match('/^[a-f\d]+$/', $id) === 1
            && file_exists($this->directory . $id);
    }

    /**
     * Write data to the session with the given ID.
     */
    public function write(string $id, string $data): void
    {
        // Check if the session exists.
        if (!array_key_exists($id, $this->sessions)) {
            $msg = 'Cannot read a session that is not open.';
            throw new \RuntimeException($msg);
        }

        // Get the session.
        $resource = $this->sessions[$id];

        // Write contents.
        try {
            $ftruncate = ftruncate($resource, 0);
            $rewind = $ftruncate && rewind($resource);
            $fwrite = $rewind && is_int(fwrite($resource, $data));
            $success = $fwrite;
            // @codeCoverageIgnoreStart
        } catch (\Throwable $t) {
            $success = false;
            $error = $t->getMessage();
        } finally {
            if (!$success) {
                $msg = 'Could not write to the session file: ';
                $msg .= $error ?? match (true) {
                    !$ftruncate => 'ftruncate() failure.',
                    !$rewind => 'rewind() failure.',
                    !$fwrite => 'fwrite() failure.',
                };
                throw new \RuntimeException($msg);
            }
            // @codeCoverageIgnoreEnd
        }
    }
}
