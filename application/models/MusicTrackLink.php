<?php

/**
 * MusicTrackLink
 *
 * @package amuzi
 * @version 1.0
 * Amuzi - Online music
 * Copyright (C) 2010-2013  Diogo Oliveira de Melo
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
class MusicTrackLink extends DZend_Model
{
    private function _getCacheKey($artist, $musicTitle)
    {
        return sha1('MusicTrackLink' . $artist . $musicTitle);
    }

    private function _getCacheIdKey($artistMusicTitleId)
    {
        return sha1('MusicTrackLinkID' . $artistMusicTitleId);
    }

    public function bond($artistMusicTitleId, $trackId, $bondName)
    {
        try {
            $artistMusicTitleRow = $this->_artistMusicTitleDb->findRowById(
                $artistMusicTitleId
            );
            $artistRow = $this->_artistDb->findRowById(
                $artistMusicTitleRow->artistId
            );
            $musicTitleRow = $this->_musicTitleDb->findRowById(
                $artistMusicTitleRow->musicTitleId
            );
            $cacheKey = $this->_getCacheKey(
                $artistRow->name, $musicTitleRow->name
            );
            $cacheIdKey = $this->_getCacheIdKey($artistMusicTitleRow->id);

            $cache = Zend_Registry::get('cache');
            $cache->remove($cacheKey);
            $cache->remove($cacheIdKey);
            $bondRow = $this->_bondModel->findRowByName($bondName);
            $currentMusicTrackLinkRow = $this->_musicTrackLinkDb->
                findRowByArtistMusicTitleIdAndTrackIdAndUserId(
                    $artistMusicTitleId,
                    $trackId,
                    isset($this->_session->user) ?
                    $this->_session->user->id : null
                );

            $currentBondRow = null;
            if (null !== $currentMusicTrackLinkRow) {
                $currentBondRow = $this->_bondModel->findRowById(
                    $currentMusicTrackLinkRow->bondId
                );

                // If the current bond has higher priority than the new bond,
                // then don't do the new bond
                if($currentBondRow->priority > $bondRow->priority)
                    return null;
                else
                    $currentMusicTrackLinkRow->delete();
            }

            $data = array(
                'artist_music_title_id' => $artistMusicTitleId,
                'track_id' => $trackId,
                'user_id' => isset($this->_session->user) ?
                    $this->_session->user->id : null,
                'bond_id' => $bondRow->id
            );

            return $this->_musicTrackLinkDb->insert($data);
        } catch (Exception $e) {
            return false;
        }
    }

    public function getTrackById($artistMusicTitleId)
    {
        $cacheKey = $this->_getCacheIdKey($artistMusicTitleId);
        $cache = Zend_Registry::get('cache');

        if (false === ($ret = $cache->load($cacheKey))) {
            $artistMusicTitleRow = $this->_artistMusicTitleDb->findRowById($artistMusicTitleId);
            $artistRow = $this->_artistDb->findRowById($artistMusicTitleRow->artistId);
            $musicTitleRow = $this->_musicTitleDb->findRowById($artistMusicTitleRow->musicTitleId);
            return $this->getTrack($artistRow->name, $musicTitleRow->name);
        }
    }

    public function getTrack($artist, $musicTitle)
    {
        $c = new DZend_Chronometer();
        $c->start();

        $cacheKey = $this->_getCacheKey($artist, $musicTitle);
        $cache = Zend_Registry::get('cache');
        $ret = null;

        if (false === ($ret = $cache->load($cacheKey))) {
            $artistMusicTitleId = $this->_artistMusicTitleModel->insert(
                $artist, $musicTitle
            );
            $cacheIdKey = $this->_getCacheIdKey($artistMusicTitleId);
            $rowSet = $this->_musicTrackLinkDb->findByArtistMusicTitleId(
                $artistMusicTitleId
            );
            $points = array();
            foreach ($rowSet as $row) {
                if (!array_key_exists($row->trackId, $points))
                    $points[$row->trackId] = 0;

                switch ($row->bondId) {
                    case 0: // search
                        $points[$row->trackId] += 1;
                        break;
                    case 1: // insert_playlist
                        $points[$row->trackId] += 4;
                        break;
                    case 2: // vote_up
                        $points[$row->trackId] += 16;
                        break;
                    case 3:
                        $points[$row->trackId] -= 16;
                        break;
                }
            }

            $trackId = 0;
            $maxPoints = -10000;
            foreach ($points as $key => $value) {
                if ($maxPoints < $value) {
                    $maxPoints = $value;
                    $trackId = $key;
                }
            }

            $ret = 0 !== $trackId ? $this->_trackModel->findRowById($trackId) : null;

            if (null !== $ret) {
                $cache->save($ret, $cacheKey);
                $cache->save($ret, $cacheIdKey);
            }

        }
        $c->stop();
        $this->_logger->debug('MusicTrackLink::getTrack time ' . $c->get());

        return $ret;
    }
}
