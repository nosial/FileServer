<?php

    namespace FileServer\Interfaces;

    interface SerializableInterface
    {
        /**
         * Converts the object to an array representation.
         *
         * @return array The array representation of the object.
         */
        public function toArray(): array;

        /**
         * Creates an object from an array representation.
         *
         * @param array $data The array representation of the object.
         * @return static The created object.
         */
        public static function fromArray(array $data): self;
    }