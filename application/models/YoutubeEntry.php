<?php

/**
 * YoutubeEntry
 *
 * @package You2Better
 * @version 1.0
 * @copyright Copyright (C) 2010 Diogo Oliveira de Melo. All rights reserved.
 * @author Diogo Oliveira de Melo <dmelo87@gmail.com>
 * @license GPL version 3
 */
class YoutubeEntry extends AbstractEntry
{
    /**
     * __construct
     *
     * @param array $entry
     * @return void
     */
    public function __construct($entry = array( ) )
    {
        $this->_fields = array(
            'id', 'title', 'content', 'you2better', 'pic', 'duration', 'youtubeUrl'
        );
        $this->_data = array();

        foreach($entry as $key => $value)
            if(in_array($key, $this->_fields))
                $this->_data[$key] = $value;
    }

    public function __set($key, $value)
    {
        parent::__set($key, $value);
        if ('id' === $key)
            $value = str_replace(
                'http://gdata.youtube.com/feeds/api/videos/', '', $value
            );
        $this->_data[$key] = $value;
    }


    /**
     * getArray
     *
     * @return void
     */
    public function getArray()
    {
        $item = array();
        foreach($this->_fields as $field)
            $item[$field] = $this->$field;

        return $item;
    }
}
