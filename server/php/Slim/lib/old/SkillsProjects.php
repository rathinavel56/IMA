<?php
/**
 * SkillsProjects
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer V3
 * @subpackage Model
 */
namespace Models;

/*
 * SkillsProjects
*/
class SkillsProjects extends AppModel
{
    protected $table = 'skills_projects';
    public $timestamps = false;
    public function skills()
    {
        return $this->belongsTo('Models\Skill', 'skill_id', 'id');
    }
}
