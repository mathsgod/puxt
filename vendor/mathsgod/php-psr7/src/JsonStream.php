<?php

namespace PHP\Psr7;

class JsonStream extends StringStream
{
    private $options;

    public function __construct($data, int $options = JSON_UNESCAPED_UNICODE)
    {
        parent::__construct(json_encode($data, $options));
        $this->options = $options;
    }

    public function write($data)
    {
        $old_data = json_decode($this->getContents(), true);

        $new_data = array_merge($old_data, $data);

        ftruncate($this->stream, 0);

        return parent::write(json_encode($new_data, $this->options));
    }
}
