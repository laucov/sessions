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

namespace Laucov\Sessions\Handlers\Interfaces;
use Laucov\Sessions\Status\SessionClosing;
use Laucov\Sessions\Status\SessionDestruction;
use Laucov\Sessions\Status\SessionOpening;

/**
 * Session handler able to open multiple sessions at once.
 */
interface SessionHandlerInterface
{
    /**
     * Close the session with the given ID.
     */
    public function close(string $id): SessionClosing;

    /**
     * Create a new session.
     */
    public function create(): string;

    /**
     * Destroy the session with the given ID.
     */
    public function destroy(string $id): SessionDestruction;

    /**
     * Open the session with the given ID.
     */
    public function open(string $id, bool $readonly): SessionOpening;

    /**
     * Read all data from the session with the given ID.
     */
    public function read(string $id): string;

    /**
     * Regenerate the session with the given ID.
     */
    public function regenerate(string $id, bool $delete_old_session): string;

    /**
     * Write data to the session with the given ID.
     */
    public function write(string $id, string $data): void;
}
