<?php

namespace Guilty\Poweroffice\Sessions;

use Session;

class LaravelSession extends AbstractSession {
    public function setAccessToken($accessToken)
    {
        Session::put($this->keyName(self::KEY_ACCESS_TOKEN), $accessToken);
    }
    public function getAccessToken()
    {
        return Session::get($this->keyName(self::KEY_ACCESS_TOKEN));
    }

    public function setRefreshToken($refreshToken)
    {
        Session::put($this->keyName(self::KEY_REFRESH_TOKEN), $refreshToken);
    }

    public function getRefreshToken()
    {
        return Session::get($this->keyName(self::KEY_REFRESH_TOKEN));
    }

    public function disconnect()
    {
        Session::forget([
            $this->keyName(self::KEY_ACCESS_TOKEN),
            $this->keyName(self::KEY_REFRESH_TOKEN),
            $this->keyName(self::KEY_EXPIRES_AT),
        ]);
    }

    public function setExpireDate(\DateTime $expireDate)
    {
        Session::put(
            $this->keyName(self::KEY_EXPIRES_AT),
            $expireDate->format(self::EXPIRES_AT_DATE_FORMAT)
        );
    }

    /** @return \DateTimeImmutable */
    public function getExpireDate()
    {
        try {
            $date = Session::get($this->keyName(self::KEY_EXPIRES_AT));
            return \DateTimeImmutable::createFromFormat(self::EXPIRES_AT_DATE_FORMAT, $date);
        } catch (\Exception $exception) {
            return null;
        }
    }
}
