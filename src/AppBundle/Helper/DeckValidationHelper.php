<?php

namespace AppBundle\Helper;

use Symfony\Component\Translation\TranslatorInterface;
use AppBundle\Model\SlotCollectionProviderInterface;

class DeckValidationHelper
{

    public function __construct (AgendaHelper $agenda_helper, TranslatorInterface $translator)
    {
        $this->agenda_helper = $agenda_helper;
        $this->translator = $translator;
    }

    public function getInvalidCards ($deck)
    {
        $invalidCards = [];
        foreach($deck->getSlots() as $slot) {
            if(!$this->canIncludeCard($deck, $slot->getCard())) {
                $invalidCards[] = $slot->getCard();
            }
        }
        return $invalidCards;
    }

    public function canIncludeCard ($deck, $card)
    {
        if($card->getFaction()->getCode() === 'neutral') {
            return true;
        }
        if($card->getFaction()->getCode() === $deck->getFaction()->getCode()) {
            return true;
        }
        if($card->getIsLoyal()) {
            return false;
        }
        foreach($deck->getSlots()->getAgendas() as $slot) {
            if($this->agenda_helper->getMinorFactionCode($slot->getCard()) === $card->getFaction()->getCode()) {
                return true;
            }
        }
        
        return false;
    }

    public function findProblem (SlotCollectionProviderInterface $deck)
    {
        $slots = $deck->getSlots();

        $plotDeck = $slots->getPlotDeck();
        $plotDeckSize = $plotDeck->countCards();

        /* @var integer $expectedPlotDeckSize Expected number of plots */
        $expectedPlotDeckSize = 7;
        foreach($slots->getAgendas() as $agenda) {
            if($agenda->getCard()->getCode() === '05045') {
                $expectedPlotDeckSize = 12;
            }
        }
        if($plotDeckSize > $expectedPlotDeckSize) {
            return 'too_many_plots';
        }
        if($plotDeckSize < $expectedPlotDeckSize) {
            return 'too_few_plots';
        }
        /* @var integer $expectedPlotDeckSpread Expected number of different plots */
        $expectedPlotDeckSpread = $expectedPlotDeckSize - 1;
        if(count($plotDeck) < $expectedPlotDeckSpread) {
            return 'too_many_different_plots';
        }
        $expectedMaxAgendaCount = 1;
        if($slots->isAlliance()) {
            $expectedMaxAgendaCount = 3;
        }
        if($slots->getAgendas()->countCards() > $expectedMaxAgendaCount) {
            return 'too_many_agendas';
        }
        if($slots->getDrawDeck()->countCards() < 60) {
            return 'too_few_cards';
        }
        foreach($slots->getCopiesAndDeckLimit() as $cardName => $value) {
            if($value['copies'] > $value['deck_limit']) {
                return 'too_many_copies';
            }
        }
        if(!empty($this->getInvalidCards($deck))) {
            return 'invalid_cards';
        }
        foreach($slots->getAgendas() as $slot) {
            $valid_agenda = $this->validateAgenda($slots, $slot->getCard());
            if(!$valid_agenda) {
                return 'agenda';
            }
        }
        return null;
    }

    public function validateAgenda (\AppBundle\Model\SlotCollectionInterface $slots, \AppBundle\Entity\Card $agenda)
    {
        switch($agenda->getCode()) {
            case '01198':
            case '01199':
            case '01200':
            case '01201':
            case '01202':
            case '01203':
            case '01204':
            case '01205':
                return $this->validateBanner($slots, $agenda);
            case '01027':
                return $this->validateFealty($slots, $agenda);
            case '04037':
            case '04038':
                return $this->validateKings($slots, $agenda);
            case '05045':
                return $this->validateRains($slots, $agenda);
            case '06018':
                return $this->validateAlliance($slots, $agenda);
            default:
                return true;
        }
    }

    public function validateBanner (\AppBundle\Model\SlotCollectionInterface $slots, \AppBundle\Entity\Card $agenda)
    {
        $minorFactionCode = $this->agenda_helper->getMinorFactionCode($agenda);
        $matchingFactionPlots = $slots->getDrawDeck()->filterByFaction($minorFactionCode)->countCards();
        if($matchingFactionPlots < 12) {
            return false;
        }
        return true;
    }

    public function validateFealty (\AppBundle\Model\SlotCollectionInterface $slots, \AppBundle\Entity\Card $agenda)
    {
        $drawDeck = $slots->getDrawDeck();
        $count = 0;
        foreach($drawDeck as $slot) {
            if($slot->getCard()->getFaction()->getCode() === 'neutral') {
                $count += $slot->getQuantity();
            }
        }
        if($count > 15) {
            return false;
        }
        return true;
    }

    public function validateKings (\AppBundle\Model\SlotCollectionInterface $slots, \AppBundle\Entity\Card $agenda)
    {
        $trait = $this->translator->trans('card.traits.' . ($agenda->getCode() === '04037' ? 'winter' : 'summer'));
        $matchingTraitPlots = $slots->getPlotDeck()->filterByTrait($trait)->countCards();
        if($matchingTraitPlots > 0) {
            return false;
        }
        return true;
    }

    public function validateRains (\AppBundle\Model\SlotCollectionInterface $slots, \AppBundle\Entity\Card $agenda)
    {
        $trait = $this->translator->trans('card.traits.scheme');
        $matchingTraitPlots = $slots->getPlotDeck()->filterByTrait($trait)->countCards();
        if($matchingTraitPlots !== 5) {
            return false;
        }
        return true;
    }
    
    public function validateAlliance (\AppBundle\Model\SlotCollectionInterface $slots, \AppBundle\Entity\Card $agenda)
    {
        $trait = $this->translator->trans('card.traits.banner');
        $agendas = $slots->getAgendas();
        $matchingTraitAgendas = $agendas->filterByTrait($trait);
        if($agendas->countCards() - $matchingTraitAgendas->countCards() !== 1) {
            return false;
        }
        return true;
    }

    public function getProblemLabel ($problem)
    {
        if(!$problem) {
            return '';
        }
        return $this->translator->trans('decks.problems.' . $problem);
    }

}