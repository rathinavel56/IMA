<?php
/**
 * SkillsUser
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * SkillsUser
*/
class SkillsUser extends AppModel
{
    protected $table = 'skills_users';
    public $timestamps = false;
    public function skills()
    {
        return $this->belongsTo('Models\Skill', 'skill_id', 'id');
    }
}
