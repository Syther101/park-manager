<?php

declare(strict_types=1);

/*
 * Copyright (c) the Contributors as noted in the AUTHORS file.
 *
 * This file is part of the Park-Manager project.
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

namespace ParkManager\Module\WebhostingModule\Domain\Account\Exception;

use ParkManager\Module\WebhostingModule\Domain\Account\WebhostingAccountId;
use function sprintf;

final class CannotRemoveActiveWebhostingAccount extends \InvalidArgumentException
{
    public static function withId(WebhostingAccountId $id): self
    {
        return new self(
            sprintf(
                'Webhosting account %s cannot be removed as it\'s still active.' .
                ' Call markForRemoval() on the WebhostingAccount instance first.',
                $id->toString()
            )
        );
    }
}
