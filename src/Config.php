<?php declare(strict_types=1);

namespace Becklyn\WebDav;

/**
 * @author Marko Vujnovic <mv@becklyn.com>
 *
 * @since  2021-08-13
 */
class Config
{
    public function __construct(private string $baseUri, private string $username, private string $password) {}

    public function baseUri() : string
    {
        return $this->baseUri;
    }

    public function username() : string
    {
        return $this->username;
    }

    public function password() : string
    {
        return $this->password;
    }
}
