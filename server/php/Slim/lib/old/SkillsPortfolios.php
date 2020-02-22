<?php
/**
 * SkillsPortfolios
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Getlancer
 * @subpackage Model
 */
namespace Models;

/*
 * SkillsPortfolios
*/
class SkillsPortfolios extends AppModel
{
    protected $table = 'skills_portfolios';
    public function portfolio()
    {
        return $this->belongsTo('Models\Portfolio', 'portfolio_id', 'id')->with('user', 'attachment');
    }
    public function skill()
    {
        return $this->belongsTo('Models\Skill', 'skill_id', 'id');
    }
}
