<?php

/**
 * Copyright © 2023 - 2024 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package Persistence
 */
namespace NoreSources\Persistence\Event;

/**
 * Event type enumeration
 */
class Event
{

	const prePersist = 'prePersist';

	const postPersist = 'postPersist';

	const preUpdate = 'preUpdate';

	const postUpdate = 'postUpdate';

	const preRemove = 'preRemove';

	const postRemove = 'postRemove';
}
