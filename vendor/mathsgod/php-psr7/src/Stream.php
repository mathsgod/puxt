<?php

namespace PHP\Psr7;

use Psr\Http\Message\StreamInterface;
use RuntimeException;
use InvalidArgumentException;

class Stream implements StreamInterface
{
    const READ_WRITE_HASH = [
        'read' => [
            'r' => true, 'w+' => true, 'r+' => true, 'x+' => true, 'c+' => true,
            'rb' => true, 'w+b' => true, 'r+b' => true, 'x+b' => true,
            'c+b' => true, 'rt' => true, 'w+t' => true, 'r+t' => true,
            'x+t' => true, 'c+t' => true, 'a+' => true
        ],
        'write' => [
            'w' => true, 'w+' => true, 'rw' => true, 'r+' => true, 'x+' => true,
            'c+' => true, 'wb' => true, 'w+b' => true, 'r+b' => true,
            'x+b' => true, 'c+b' => true, 'w+t' => true, 'r+t' => true,
            'x+t' => true, 'c+t' => true, 'a' => true, 'a+' => true
        ]
    ];

    protected $stream;

    public function __construct($stream = null)
    {
        if ($stream === null) {
            $stream = fopen("php://memory", "r+");
        } elseif (is_string($stream)) {
            $str = $stream;
            $stream = fopen("php://memory", "r+");
            fwrite($stream, $str);
        }

        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Stream must be a resource');
        }

        $this->stream = $stream;
    }


    public function getSize()
    {
        if (!isset($this->stream)) {
            return null;
        }

        $stats = fstat($this->stream);
        if (isset($stats['size'])) {
            return $stats['size'];
        }
    }

    public function getContents()
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }
        fseek($this->stream, 0);
        $contents = stream_get_contents($this->stream);
        if ($contents === false) {
            throw new RuntimeException('Unable to read stream contents');
        }
        return $contents;
    }

    public function getMetadata($key = null)
    {
        $meta = stream_get_meta_data($this->stream);
        if (is_null($key)) {
            return $meta;
        }
        return $meta[$key];
    }

    public function close()
    {
        if (isset($this->stream)) {
            fclose($this->stream);
        }
        $this->detach();
    }

    public function detach()
    {
        $old_stream = $this->stream;
        $this->stream = null;
        return $old_stream;
    }

    public function eof()
    {
        return $this->stream ? feof($this->stream) : true;
    }

    public function isSeekable()
    {
        $meta = stream_get_meta_data($this->stream);
        return  $meta['seekable'];
    }

    public function isWritable()
    {
        if (is_null($this->stream)) return false;
        $meta = stream_get_meta_data($this->stream);
        return isset(self::READ_WRITE_HASH["write"][$meta['mode']]);
    }

    public function isReadable()
    {
        if (is_null($this->stream)) return false;
        $meta = stream_get_meta_data($this->stream);
        return isset(self::READ_WRITE_HASH["read"][$meta['mode']]);
    }

    public function tell()
    {
        if (!$this->stream || ($position = ftell($this->stream)) === false) {
            throw new RuntimeException('Could not get the position of the pointer in stream');
        }
        return $position;
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        // Note that fseek returns 0 on success!
        if (!$this->isSeekable() || fseek($this->stream, $offset, $whence) === -1) {
            throw new RuntimeException('Could not seek in stream');
        }
    }

    public function rewind()
    {
        if (!$this->isSeekable() || rewind($this->stream) === false) {
            throw new RuntimeException('Could not rewind stream');
        }
    }

    public function write($string)
    {
        if (!$this->isWritable() || ($written = fwrite($this->stream, $string)) === false) {
            throw new RuntimeException('Could not write to stream');
        }
        return $written;
    }

    public function read($length)
    {
        if (!$this->isReadable() || ($data = fread($this->stream, $length)) === false) {
            throw new RuntimeException('Could not read from stream');
        }
        return $data;
    }

    public function __toString()
    {
        if (!isset($this->stream)) {
            return "";
        }

        try {
            $this->rewind();
            return $this->getContents();
        } catch (RuntimeException $e) {
        }

        return "";
    }
}
