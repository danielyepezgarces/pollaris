<?php

namespace App\Tests\Validator;

use App\Entity\Answer;
use App\Entity\Poll;
use App\Entity\Proposal;
use App\Entity\Vote;
use App\Validator\MaxVotes;
use App\Validator\MaxVotesValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class MaxVotesValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): MaxVotesValidator
    {
        return new MaxVotesValidator();
    }

    public function testEffectiveMaxVotesResolution(): void
    {
        $poll = new Poll();
        $poll->setMaxVotes(5);

        $pWithOwnLimit = new Proposal();
        $pWithOwnLimit->setMaxVotes(2);
        $poll->addProposal($pWithOwnLimit);

        $pWithPollFallback = new Proposal();
        $poll->addProposal($pWithPollFallback);

        $this->assertSame(2, $pWithOwnLimit->getEffectiveMaxVotes());
        $this->assertSame(5, $pWithPollFallback->getEffectiveMaxVotes());

        $poll->setMaxVotes(null);
        $this->assertSame(2, $pWithOwnLimit->getEffectiveMaxVotes());
        $this->assertNull($pWithPollFallback->getEffectiveMaxVotes());
    }

    public function testIndividualMaxVotesBlocksWhenFullAndAllowsOthers(): void
    {
        $poll = new Poll();

        $pMorning = $this->createProposalWithId(10, 'Morning', 1);
        $pAfternoon = $this->createProposalWithId(11, 'Afternoon', 3);
        $poll->addProposal($pMorning)->addProposal($pAfternoon);

        // Existing vote takes the 1 spot for Morning and 1 of 3 spots for Afternoon
        $existingVote = new Vote();
        $existingVote->setAuthorName('Alice');
        $poll->addVote($existingVote);

        $a1 = (new Answer())->setProposal($pMorning)->setValue('yes')->setVote($existingVote);
        $a2 = (new Answer())->setProposal($pAfternoon)->setValue('yes')->setVote($existingVote);
        $pMorning->addAnswer($a1);
        $pAfternoon->addAnswer($a2);

        // New vote tries to vote yes on both Morning (full: 1/1) and Afternoon (available: 1/3)
        $newVote = new Vote();
        $newVote->setAuthorName('Bob');
        $newVote->setPoll($poll);

        $newA1 = (new Answer())->setProposal($pMorning)->setValue('yes')->setVote($newVote);
        $newA2 = (new Answer())->setProposal($pAfternoon)->setValue('yes')->setVote($newVote);
        $newVote->addAnswer($newA1)->addAnswer($newA2);

        $constraint = new MaxVotes();
        $this->validator->validate($newVote, $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ max }}', '1')
            ->atPath('property.path.answers[0].value')
            ->assertRaised();
    }

    public function testIndividualMaxVotesOverridesGlobalPollMaxVotes(): void
    {
        $poll = new Poll();
        $poll->setMaxVotes(1);

        $pSpecial = $this->createProposalWithId(20, 'Special Shift', 2);
        $poll->addProposal($pSpecial);

        // 1 existing vote (at global limit of 1, but individual limit is 2)
        $existingVote = new Vote();
        $existingVote->setAuthorName('Alice');
        $poll->addVote($existingVote);
        $a1 = (new Answer())->setProposal($pSpecial)->setValue('yes')->setVote($existingVote);
        $pSpecial->addAnswer($a1);

        // Second vote should be ALLOWED because individual maxVotes is 2
        $newVote = new Vote();
        $newVote->setAuthorName('Bob');
        $newVote->setPoll($poll);
        $newA1 = (new Answer())->setProposal($pSpecial)->setValue('yes')->setVote($newVote);
        $newVote->addAnswer($newA1);

        $this->validator->validate($newVote, new MaxVotes());
        $this->assertNoViolation();
    }

    private function createProposalWithId(int $id, string $label, ?int $maxVotes): Proposal
    {
        $proposal = new Proposal();
        $proposal->setLabel($label);
        $proposal->setMaxVotes($maxVotes);

        $ref = new \ReflectionProperty(Proposal::class, 'id');
        $ref->setValue($proposal, $id);

        return $proposal;
    }
}
