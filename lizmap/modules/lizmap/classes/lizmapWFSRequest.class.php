<?php
/**
* Manage OGC request.
* @package   lizmap
* @subpackage lizmap
* @author    3liz
* @copyright 2015 3liz
* @link      http://3liz.com
* @license Mozilla Public License : http://www.mozilla.org/MPL/
*/

jClasses::inc('lizmap~lizmapProxy');
jClasses::inc('lizmap~lizmapOGCRequest');
class lizmapWFSRequest extends lizmapOGCRequest {
    
    protected $tplExceptions = 'lizmap~wfs_exception';
    
    protected function getcapabilities ( ) {
        $result = parent::getcapabilities();
        if ( $result->cached )
            return $result;
        
        $data = $result->data;
        if ( empty( $data ) or floor( $result->code / 100 ) >= 4 ) {
            jMessage::add('Server Error !', 'Error');
            return $this->serviceException();
        }

        if ( preg_match( '#ServiceExceptionReport#i', $data ) )
            return $result;
        
        // Replace qgis server url in the XML (hide real location)
        $sUrl = jUrl::getFull(
          "lizmap~service:index",
          array("repository"=>$this->repository->getKey(), "project"=>$this->project->getKey())
        );
        $sUrl = str_replace('&', '&amp;', $sUrl);
        preg_match('/Request.*Request/s', $data, $matches);
        $matches[0] = preg_replace('/onlineResource=".*"/', 'onlineResource="'.$sUrl.'&amp;"', $matches[0]);
        $data = preg_replace('/Request.*Request/s', $matches[0], $data);
        
        // Add response to cache
        $cacheId = $this->repository->getKey().'_'.$this->project->getKey().'_'.$this->param('service');
        $newhash = md5_file( realpath($this->repository->getPath()) . '/' . $this->project->getKey() . ".qgs" );
        jCache::set($cacheId . '_hash', $newhash);
        jCache::set($cacheId . '_mime', $result->mime);
        jCache::set($cacheId . '_data', $data);
        
        return (object) array(
            'code' => 200,
            'mime' => $result->mime,
            'data' => $data,
            'cached' => False
        );
    }
    
    function describefeaturetype(){
        // Construction of the request url : base url + parameters
        $url = $this->services->wmsServerURL.'?';
        $bparams = http_build_query($this->params);
        $querystring = $url . $bparams;

        // Get remote data
        $getRemoteData = lizmapProxy::getRemoteData(
          $querystring,
          $this->services->proxyMethod,
          $this->services->debugMode
        );
        $data = $getRemoteData[0];
        $mime = $getRemoteData[1];
        $code = $getRemoteData[2];
        
        return (object) array(
            'code' => $code,
            'mime' => $mime,
            'data' => $data,
            'cached' => False
        );
    }
    
    function getfeature() {
        // add outputformat if not provided
        $output = $this->param('outputformat');
        if(!$output)
            $this->params['outputformat'] = 'GML2';

        // Construction of the request url : base url + parameters
        $url = $this->services->wmsServerURL.'?';
        $bparams = http_build_query($this->params);
        $querystring = $url . $bparams;

        // Get remote data
        $getRemoteData = lizmapProxy::getRemoteData(
            $querystring,
            $this->services->proxyMethod,
            $this->services->debugMode
        );
        $data = $getRemoteData[0];
        $mime = $getRemoteData[1];
        $code = $getRemoteData[2];

        if ( $mime == 'text/plain' && strtolower( $this->param('outputformat') ) == 'geojson' ) {
            $mime = 'text/json';
            $layer = $this->project->findLayerByName( $this->params['typename'] );
            if ( $layer != null ) {
                $layer = $this->project->getLayer( $layer->id );
                $aliases = $layer->getAliasFields();
                $layer = json_decode( $data );
                $layer->aliases = (object) $aliases;
                $data = json_encode( $layer );
            }
        }
        
        return (object) array(
            'code' => $code,
            'mime' => $mime,
            'data' => $data,
            'cached' => False
        );
    }
}