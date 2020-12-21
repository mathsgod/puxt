<?php

namespace PHP\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    protected $host = '';
    protected $scheme = '';
    protected $user = '';
    protected $password;
    protected $port;
    protected $path = '';
    protected $query = '';
    protected $fragment = '';


    const DEFAULT_PORTS = [
        'http'  => 80,
        'https' => 443,
        'ftp' => 21,
        'gopher' => 70,
        'nntp' => 119,
        'news' => 119,
        'telnet' => 23,
        'tn3270' => 23,
        'imap' => 143,
        'pop' => 110,
        'ldap' => 389,
    ];

    public function __construct(string $url = null)
    {
        $parsed_url = parse_url($url);
        if (isset($parsed_url["scheme"])) {
            $this->scheme = $parsed_url["scheme"];
        }

        if (isset($parsed_url["host"])) {
            $this->host = $parsed_url["host"];
        }

        if (isset($parsed_url["port"])) {
            $this->port = $parsed_url["port"];
        }

        if (isset($parsed_url["user"])) {
            $this->user = $parsed_url["user"];
        }

        if (isset($parsed_url["pass"])) {
            $this->password = $parsed_url["pass"];
        }

        if (isset($parsed_url["path"])) {
            $this->path = $parsed_url["path"];
        }

        if (isset($parsed_url["query"])) {
            $this->query = $parsed_url["query"];
        }

        if (isset($parsed_url["fragment"])) {
            $this->fragment = $parsed_url["fragment"];
        }
    }

    public function getScheme()
    {
        return $this->scheme;
    }

    public function getFragment()
    {
        return $this->fragment;
    }

    public function getAuthority()
    {
        $userInfo = $this->getUserInfo();
        $host = $this->getHost();
        $port = $this->getPort();
        return ($userInfo ? $userInfo . '@' : '') . $host . ($port !== null ? ':' . $port : '');
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getPort()
    {
        if (self::DEFAULT_PORTS[$this->getScheme()] == $this->port) {
            return null;
        }
        return $this->port;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getUserInfo()
    {
        $userInfo = $this->user;
        $userInfo .= $this->password ? ":" . $this->password : "";
        return $userInfo;
    }

    public function withFragment($fragment)
    {
        $clone = clone $this;
        $clone->fragment = $fragment;
        return $clone;
    }

    public function withHost($host)
    {
        if (!is_string($host)) {
            throw new InvalidArgumentException("invalid hostnames");
        }
        $clone = clone $this;
        $clone->host = $host;
        return $clone;
    }

    public function withPath($path)
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException("invalid paths");
        }

        $clone = clone $this;
        $clone->path = $path;
        return $clone;
    }

    public function withPort($port)
    {
        if (isset($port)) {
            if ($port < 1 || $port > 65535) {
                throw new InvalidArgumentException("invalid ports");
            }
        }
        $clone = clone $this;
        $clone->port = $port;
        return $clone;
    }

    public function withQuery($query)
    {
        if (!is_string($query)) {
            throw new InvalidArgumentException("invalid query strings");
        }
        $clone = clone $this;
        $clone->query = $query;
        return $clone;
    }

    public function withScheme($scheme)
    {
        if (!is_string($scheme)) {
            throw new InvalidArgumentException("invalid schemes");
        }
        $clone = clone $this;
        $clone->scheme = $scheme;
        return $clone;
    }

    public function withUserInfo($user, $password = null)
    {
        $clone = clone $this;
        $clone->user = (string)$user;
        $clone->password = $password;
        return $clone;
    }

    public function __toString()
    {
        $scheme = $this->getScheme();
        $authority = $this->getAuthority();
        $path = $this->getPath();
        $query = $this->getQuery();
        $fragment = $this->getFragment();

        //If the path is rootless and an authority is present, the path MUST be prefixed by "/".
        if ($path[0] != "/" && $authority) {
            $path = "/" . $path;
        }
        //If the path is starting with more than one "/" and no authority is present, the starting slashes MUST be reduced to one.
        if (!$authority && substr($path, 0, 2) == "//") {
            $path = substr($path, 1);
        }

        return ($scheme ? $scheme . ':' : '')
            . ($authority ? '//' . $authority : '')
            . $path
            . ($query ? '?' . $query : '')
            . ($fragment ? '#' . $fragment : '');
    }
}
