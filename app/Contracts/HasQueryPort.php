<?php

namespace App\Contracts;

/**
 * Marker interface for game handlers that use a configurable Steam query port.
 *
 * Handlers implementing this will have `query_port` validated and persisted
 * on the Server model. Non-implementing handlers will have a null query_port.
 */
interface HasQueryPort {}
