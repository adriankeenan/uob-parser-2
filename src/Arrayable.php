<?php

namespace UoBParser;

interface Arrayable {

    /**
     * Get an array representing this object suitable for serialisation.
     * @return array
     */
    public function toArray();

}