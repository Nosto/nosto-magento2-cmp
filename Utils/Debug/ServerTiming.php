<?php
/**
 * Copyright (c) 2019, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2019 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Cmp\Utils\Debug;

class ServerTiming
{
    public const HEADER_NAME = 'X-Server-Timing';

    /**
     * @var array holds measurements for each request
     */
    private $times = [];

    /**
     * @var ServerTiming singleton
     */
    private static $instance;

    /**
     * ServerTiming constructor.
     */
    private function __construct()
    {
        // Private
    }

    /**
     * Call user function, measure how long it takes and add time to array
     * @param callable $fn
     * @param string $name
     */
    public function instrument(callable $fn, $name)
    {
        $start = microtime(true);
        $fn();
        $stop = microtime(true);
        $this->times[$name] = round(($stop - $start) * 1000);
    }

    /**
     * Builds and returns a string with all times/names in self::$times[]
     * @return string
     */
    public function build()
    {
        $headers = [];
        foreach ($this->times as $key => $time) {
            $headers[] = sprintf('%s;dur=%d', $key, $time);
        }
        $this->times = [];
        return implode(',', $headers);
    }

    /**
     * Returns singleton instance
     * @return ServerTiming
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new ServerTiming();
        }
        return self::$instance;
    }

    /**
     * Returns if self::$times array has data
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->times);
    }
}
