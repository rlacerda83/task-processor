<?php

namespace App\Helpers;

class Crypt
{
    /**
     * @param $text
     * @param $secretKey
     * @return string
     */
    public static function encrypt ($text, $secretKey) {
        return trim(
            base64_encode(
                mcrypt_encrypt(
                    MCRYPT_RIJNDAEL_256,
                    $secretKey,
                    $text,
                    MCRYPT_MODE_ECB,
                    mcrypt_create_iv(
                        mcrypt_get_iv_size(
                            MCRYPT_RIJNDAEL_256,
                            MCRYPT_MODE_ECB
                        ),
                        MCRYPT_RAND
                    )
                )
            )
        );
    }

    /**
     * @param $text
     * @param $secretKey
     * @return string
     */
    public static function decrypt ($text, $secretKey) {
        return trim(
            mcrypt_decrypt(
                MCRYPT_RIJNDAEL_256,
                $secretKey,
                base64_decode($text), 
                MCRYPT_MODE_ECB, 
                mcrypt_create_iv(
                    mcrypt_get_iv_size(
                        MCRYPT_RIJNDAEL_256, 
                        MCRYPT_MODE_ECB
                    ), MCRYPT_RAND
                )
            )
        );
    }
}