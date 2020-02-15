<?php
/**
 * UploadHoster
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * UploadHoster
*/
class UploadHoster extends AppModel
{
    protected $table = 'upload_hosters';
    public $rules = array();
    public function scopeFilter($query, $params = array())
    {
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $search = $params['q'];
            $query->orWhereHas('upload_service', function ($q) use ($search) {
                $q->where('name', 'ilike', "%$search%");
            });
            $query->orWhereHas('upload_service_type', function ($q) use ($search) {
                $q->where('name', 'ilike', "%$search%");
            });
            $query->orWhereHas('upload_status', function ($q) use ($search) {
                $q->where('name', 'ilike', "%$search%");
            });
        }
    }
    public function upload_service()
    {
        return $this->belongsTo('Models\UploadService', 'upload_service_id', 'id');
    }
    public function upload_service_type()
    {
        return $this->belongsTo('Models\UploadServiceType', 'upload_service_type_id', 'id');
    }
}
