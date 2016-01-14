<?php

/*
 * This file is part of the GeckoPackages.
 *
 * (c) GeckoPackages https://github.com/GeckoPackages
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace GeckoPackages\MemcacheMock;

/**
 * When configured to do so the MemcachedMock thrown this if an assert fails.
 *
 * @author SpacePossum
 */
class MemcachedMockAssertException extends \UnexpectedValueException
{
    //
}
