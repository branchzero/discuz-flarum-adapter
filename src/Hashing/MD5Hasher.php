<?php

namespace Branchzero\DiscuzFlarumAdapter\Hashing;

use Illuminate\Contracts\Hashing\Hasher as HasherContract;

class MD5Hasher implements HasherContract
{
    /**
     * Hash the given value.
     *
     * @param  string  $value
     * @param  array   $options
     * @return string
     *
     * @throws \RuntimeException
     */
    public function make($value, array $options = [])
    {
        $salt = str_random(6);

        return md5(md5($value) . $salt) . '$' . $salt;
    }

    /**
     * Check the given plain value against a hash.
     *
     * @param  string  $value
     * @param  string  $hashedValue
     * @param  array   $options
     * @return bool
     */
    public function check($value, $hashedValue, array $options = [])
    {
        if (strlen($hashedValue) === 0) {
            return false;
        }

        $hashedValue = explode('$', $hashedValue);
        if (count($hashedValue) != 2) {
            return false;
        }

        return md5(md5($value) . $hashedValue[1]) == $hashedValue[0];
    }

    /**
     * Check if the given hash has been hashed using the given options.
     *
     * @param  string  $hashedValue
     * @param  array   $options
     * @return bool
     */
    public function needsRehash($hashedValue, array $options = [])
    {
        return false;
    }
}
