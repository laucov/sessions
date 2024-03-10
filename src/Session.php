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

namespace Laucov\Sessions;

use Laucov\Arrays\ArrayBuilder;
use Laucov\Sessions\Handlers\Interfaces\SessionHandlerInterface;
use Laucov\Sessions\Status\SessionClosing;
use Laucov\Sessions\Status\SessionDestruction;
use Laucov\Sessions\Status\SessionOpening;

/**
 * Allows reading and writing a specific session.
 */
class Session
{
    /**
     * Array builder.
     */
    protected ArrayBuilder $values;

    /**
     * Create the session instance.
     */
    public function __construct(
        /**
         * Session handler.
         */
        protected SessionHandlerInterface $handler,

        /**
         * Session ID.
         */
        public string $id,
    ) {
    }

    /**
     * Close session.
     */
    public function close(): static
    {
        // Close session.
        $close = $this->handler->close($this->id);
        if ($close !== SessionClosing::CLOSED) {
            $message = 'Could not close the session: ' . match ($close) {
                SessionClosing::NOT_OPEN => 'Session not open.',
            };
            throw new \RuntimeException($message);
        }

        return $this;
    }

    /**
     * Save changes.
     */
    public function commit($close = true): static
    {
        // Serialize contents.
        $array = $this->values->getArray();
        $data = count($array) > 0 ? serialize($array) : '';

        // Write to the session.
        $this->handler->write($this->id, $data);
        if ($close) {
            $this->close();
        }

        return $this;
    }

    /**
     * Destroy this session.
     */
    public function destroy(): static
    {
        // Destroy the session.
        $result = $this->handler->destroy($this->id);
        if ($result !== SessionDestruction::DESTROYED) {
            $message = 'Could not destroy the session: ' . match ($result) {
                SessionDestruction::NOT_OPEN => 'Session not open.',
            };
            throw new \RuntimeException($message);
        }

        // Remove ID.
        $this->id = '';

        return $this;
    }

    /**
     * Get a session value.
     */
    public function get(string $path, mixed $default_value = null): mixed
    {
        return $this->values->getValue(explode('.', $path), $default_value);
    }

    /**
     * Open this session.
     */
    public function open($readonly = false): static
    {
        // Open the session.
        $result = $this->handler->open($this->id, $readonly);
        if ($result !== SessionOpening::OPEN) {
            $message = 'Could not open the session: ' . match ($result) {
                SessionOpening::ALREADY_OPEN => 'Session already open.',
                SessionOpening::NOT_FOUND => 'Session not found.',
            };
            throw new \RuntimeException($message);
        }

        // Get and unserialize the contents.
        $data = $this->handler->read($this->id);
        $array = strlen($data) > 0 ? unserialize($data) : [];

        // Cache data.
        $this->values = new ArrayBuilder($array);

        return $this;
    }

    /**
     * Regenerate this session.
     */
    public function regenerate(bool $delete_old_session): static
    {
        $this->id = $this->handler->regenerate($this->id, $delete_old_session);
        return $this;
    }

    /**
     * Set a value for this session.
     */
    public function set(string $path, mixed $value): static
    {
        $this->values->setValue(explode('.', $path), $value);
        return $this;
    }

    /**
     * Remove all values from this session.
     */
    public function unset(): static
    {
        $this->values = new ArrayBuilder([]);
        return $this;
    }
}
