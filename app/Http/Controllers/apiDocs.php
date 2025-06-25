<?php

/**
 * @OA\Info(
 * version="1.0.0",
 * title="Giang API",
 * description="description",
 * @OA\Contact(
 * email="giangact.dev@gmail.com"
 * ),
 * @OA\License(
 * name="Apache 2.0",
 * url="http://www.apache.org/licenses/LICENSE-2.0.html"
 * )
 * )
 */

/**
 * @OA\Server(
 * url="http://giang.stayhere.tk/api",
 * description="Staging API v1"
 * )
 */

/**
 * @OA\Server(
 * url="http://giang.test:8080/api",
 * description="Local test API v1"
 * )
 */



/** @OA\SecurityScheme(
 * securityScheme="bearerAuth",
 * in="header",
 * name="bearerAuth",
 * type="http",
 * scheme="bearer",
 * )
 */
