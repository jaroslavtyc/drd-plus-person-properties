<?php
namespace DrdPlus\Tests\PropertiesByLevels;

use DrdPlus\Codes\GenderCode;
use DrdPlus\Codes\ProfessionCode;
use DrdPlus\Codes\PropertyCode;
use DrdPlus\Codes\RaceCode;
use DrdPlus\Codes\SubRaceCode;
use DrdPlus\Person\ProfessionLevels\ProfessionLevel;
use DrdPlus\Person\ProfessionLevels\ProfessionLevels;
use DrdPlus\Properties\Body\Height;
use DrdPlus\Properties\Combat\Attack;
use DrdPlus\Properties\Combat\DefenseNumberAgainstShooting;
use DrdPlus\Properties\Combat\DefenseNumber;
use DrdPlus\Properties\Combat\FightNumber;
use DrdPlus\Properties\Combat\Shooting;
use DrdPlus\PropertiesByFate\PropertiesByFate;
use DrdPlus\PropertiesByLevels\FirstLevelProperties;
use DrdPlus\PropertiesByLevels\NextLevelsProperties;
use DrdPlus\PropertiesByLevels\PropertiesByLevels;
use DrdPlus\Professions\Profession;
use DrdPlus\Properties\Base\Agility;
use DrdPlus\Properties\Base\Charisma;
use DrdPlus\Properties\Base\Intelligence;
use DrdPlus\Properties\Base\Knack;
use DrdPlus\Properties\Base\Strength;
use DrdPlus\Properties\Base\Will;
use DrdPlus\Properties\Body\Age;
use DrdPlus\Properties\Body\HeightInCm;
use DrdPlus\Properties\Body\Size;
use DrdPlus\Properties\Body\WeightInKg;
use DrdPlus\Properties\Derived\Beauty;
use DrdPlus\Properties\Derived\Dangerousness;
use DrdPlus\Properties\Derived\Dignity;
use DrdPlus\Properties\Derived\Endurance;
use DrdPlus\Properties\Derived\FatigueBoundary;
use DrdPlus\Properties\Derived\Senses;
use DrdPlus\Properties\Derived\Speed;
use DrdPlus\Properties\Derived\Toughness;
use DrdPlus\Properties\Derived\WoundBoundary;
use DrdPlus\Races\Humans\CommonHuman;
use DrdPlus\Races\Race;
use DrdPlus\Tables\Tables;
use DrdPlus\Calculations\SumAndRound;
use Granam\Integer\IntegerObject;

class PropertiesByLevelsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     * @dataProvider getCombination
     * @param Race $race
     * @param GenderCode $genderCode
     * @param PropertiesByFate $propertiesByFate
     * @param ProfessionLevels $professionLevels
     * @param Tables $tables
     * @param WeightInKg $weightInKgAdjustment
     * @param HeightInCm $heightInCmAdjustment
     * @param Age $age
     * @param int $expectedStrength
     * @param int $expectedAgility
     * @param int $expectedKnack
     * @param int $expectedWill
     * @param int $expectedIntelligence
     * @param int $expectedCharisma
     */
    public function I_can_create_properties_for_any_combination(
        Race $race,
        GenderCode $genderCode,
        PropertiesByFate $propertiesByFate,
        ProfessionLevels $professionLevels,
        Tables $tables,
        WeightInKg $weightInKgAdjustment,
        HeightInCm $heightInCmAdjustment,
        Age $age,
        $expectedStrength,
        $expectedAgility,
        $expectedKnack,
        $expectedWill,
        $expectedIntelligence,
        $expectedCharisma
    )
    {
        $properties = new PropertiesByLevels(
            $race,
            $genderCode,
            $propertiesByFate,
            $professionLevels,
            $weightInKgAdjustment,
            $heightInCmAdjustment,
            $age,
            $tables
        );

        self::assertInstanceOf(FirstLevelProperties::class, $properties->getFirstLevelProperties());
        self::assertInstanceOf(NextLevelsProperties::class, $properties->getNextLevelsProperties());

        self::assertSame($expectedStrength, $properties->getStrength()->getValue(), "$race $genderCode");
        self::assertSame($expectedAgility, $properties->getAgility()->getValue(), "$race $genderCode");
        self::assertSame($expectedKnack, $properties->getKnack()->getValue(), "$race $genderCode");
        self::assertSame($expectedWill, $properties->getWill()->getValue(), "$race $genderCode");
        self::assertSame($expectedIntelligence, $properties->getIntelligence()->getValue(), "$race $genderCode");
        self::assertSame($expectedCharisma, $properties->getCharisma()->getValue(), "$race $genderCode");

        self::assertSame($weightInKgAdjustment, $properties->getWeightInKgAdjustment());
        self::assertGreaterThan($weightInKgAdjustment->getValue(), $properties->getWeightInKg()->getValue(), "$race $genderCode");
        self::assertSame($heightInCmAdjustment, $properties->getHeightInCmAdjustment());
        self::assertGreaterThan($heightInCmAdjustment->getValue(), $properties->getHeightInCm()->getValue(), "$race $genderCode");
        self::assertEquals($expectedHeight = new Height($properties->getHeightInCm(), $tables->getDistanceTable()), $properties->getHeight());
        self::assertSame($age, $properties->getAge());
        $expectedToughness = new Toughness(Strength::getIt($expectedStrength), $race->getRaceCode(), $race->getSubraceCode(), $tables->getRacesTable());
        self::assertInstanceOf(Toughness::class, $properties->getToughness());
        self::assertSame($expectedToughness->getValue(), $properties->getToughness()->getValue(), "$race $genderCode");
        $expectedEndurance = new Endurance(Strength::getIt($expectedStrength), Will::getIt($expectedWill));
        self::assertInstanceOf(Endurance::class, $properties->getEndurance());
        self::assertSame($expectedEndurance->getValue(), $properties->getEndurance()->getValue(), "$race $genderCode");
        $expectedSize = Size::getIt($race->getSize($genderCode, $tables) + 1); /* size bonus by strength */
        self::assertInstanceOf(Size::class, $properties->getSize(), "$race $genderCode");
        self::assertSame($expectedSize->getValue(), $properties->getSize()->getValue(), "$race $genderCode");
        $expectedSpeed = new Speed(Strength::getIt($expectedStrength), Agility::getIt($expectedAgility), $expectedHeight);
        self::assertInstanceOf(Speed::class, $properties->getSpeed(), "$race $genderCode");
        self::assertSame($expectedSpeed->getValue(), $properties->getSpeed()->getValue(), "$race $genderCode");
        $expectedSenses = new Senses(
            Knack::getIt($expectedKnack),
            RaceCode::getIt($race->getRaceCode()),
            SubRaceCode::getIt($race->getSubraceCode()),
            $tables->getRacesTable()
        );
        self::assertInstanceOf(Senses::class, $properties->getSenses());
        self::assertSame($expectedSenses->getValue(), $properties->getSenses()->getValue(), "$race $genderCode");
        $expectedBeauty = new Beauty(Agility::getIt($expectedAgility), Knack::getIt($expectedKnack), Charisma::getIt($expectedCharisma));
        self::assertInstanceOf(Beauty::class, $properties->getBeauty());
        self::assertSame($expectedBeauty->getValue(), $properties->getBeauty()->getValue(), "$race $genderCode");
        $expectedDangerousness = new Dangerousness(Strength::getIt($expectedStrength), Will::getIt($expectedWill), Charisma::getIt($expectedCharisma));
        self::assertInstanceOf(Dangerousness::class, $properties->getDangerousness());
        self::assertSame($expectedDangerousness->getValue(), $properties->getDangerousness()->getValue(), "$race $genderCode");
        $expectedDignity = new Dignity(Intelligence::getIt($expectedIntelligence), Will::getIt($expectedWill), Charisma::getIt($expectedCharisma));
        self::assertInstanceOf(Dignity::class, $properties->getDignity());
        self::assertSame($expectedDignity->getValue(), $properties->getDignity()->getValue(), "$race $genderCode");

        $expectedFightValue = $expectedAgility /* fighter */ + (SumAndRound::ceil($expectedHeight->getValue() / 3) - 2);
        self::assertInstanceOf(FightNumber::class, $properties->getFightNumber());
        self::assertSame($expectedFightValue, $properties->getFightNumber()->getValue(), "$race $genderCode with height $expectedHeight");
        $expectedAttack = new Attack(Agility::getIt($expectedAgility));
        self::assertInstanceOf(Attack::class, $properties->getAttack());
        self::assertSame($expectedAttack->getValue(), $properties->getAttack()->getValue(), "$race $genderCode");
        $expectedShooting = new Shooting(Knack::getIt($expectedKnack));
        self::assertInstanceOf(Shooting::class, $properties->getShooting());
        self::assertSame($expectedShooting->getValue(), $properties->getShooting()->getValue(), "$race $genderCode");
        $expectedDefense = new DefenseNumber(Agility::getIt($expectedAgility));
        self::assertInstanceOf(DefenseNumber::class, $properties->getDefenseNumber());
        self::assertSame($expectedDefense->getValue(), $properties->getDefenseNumber()->getValue(), "$race $genderCode");
        $expectedDefenseAgainstShooting = new DefenseNumberAgainstShooting($expectedDefense, $expectedSize);
        self::assertInstanceOf(DefenseNumberAgainstShooting::class, $properties->getDefenseAgainstShooting());
        self::assertSame($expectedDefenseAgainstShooting->getValue(), $properties->getDefenseAgainstShooting()->getValue(), "$race $genderCode");

        $expectedWoundBoundary = new WoundBoundary($expectedToughness, $tables->getWoundsTable());
        self::assertInstanceOf(WoundBoundary::class, $properties->getWoundBoundary());
        self::assertSame($expectedWoundBoundary->getValue(), $properties->getWoundBoundary()->getValue());
        $expectedFatigueBoundary = new FatigueBoundary($expectedEndurance, $tables->getFatigueTable());
        self::assertInstanceOf(FatigueBoundary::class, $properties->getFatigueBoundary());
        self::assertSame($expectedFatigueBoundary->getValue(), $properties->getFatigueBoundary()->getValue());
    }

    public function getCombination()
    {
        $male = GenderCode::getIt(GenderCode::MALE);
        $female = GenderCode::getIt(GenderCode::FEMALE);
        $propertiesByFate = $this->createPropertiesByFate();
        $professionLevels = $this->createProfessionLevels();
        $tables = new Tables();
        $weightInKgAdjustment = WeightInKg::getIt(0.001);
        $heightInCm = HeightInCm::getIt(123.4);
        $age = Age::getIt(15);
        $baseOfExpectedStrength = $professionLevels->getNextLevelsStrengthModifier() + 3; /* default max strength increment */
        $baseOfExpectedAgility = $professionLevels->getNextLevelsAgilityModifier() + 3; /* default max agility increment */
        $baseOfExpectedKnack = $professionLevels->getNextLevelsKnackModifier() + 3; /* default max knack increment */
        $baseOfExpectedWill = $professionLevels->getNextLevelsWillModifier() + 3; /* default max knack increment */
        $baseOfExpectedIntelligence = $professionLevels->getNextLevelsIntelligenceModifier() + 3; /* default max knack increment */
        $baseOfExpectedCharisma = $professionLevels->getNextLevelsCharismaModifier() + 3; /* default max charisma increment */

        return [
            [
                $commonHuman = CommonHuman::getIt(), $male, $propertiesByFate, $professionLevels, $tables,
                $weightInKgAdjustment, $heightInCm, $age, $baseOfExpectedStrength, $baseOfExpectedAgility, $baseOfExpectedKnack,
                $baseOfExpectedWill, $baseOfExpectedIntelligence, $baseOfExpectedCharisma,
            ],
            [
                $commonHuman, $female, $propertiesByFate, $professionLevels, $tables, $weightInKgAdjustment,
                $heightInCm, $age,
                $baseOfExpectedStrength - 1 /* human female */, $baseOfExpectedAgility, $baseOfExpectedKnack,
                $baseOfExpectedWill, $baseOfExpectedIntelligence, $baseOfExpectedCharisma + 1 /* human female */,
            ],
            // ... no reason to check every race
        ];
    }

    /**
     * @return PropertiesByFate|\Mockery\MockInterface
     */
    private function createPropertiesByFate()
    {
        $propertiesByFate = \Mockery::mock(PropertiesByFate::class);
        $propertiesByFate->shouldReceive('getProperty')
            ->with(PropertyCode::getIt(PropertyCode::STRENGTH))
            ->andReturn($strength = new IntegerObject(123));
        $propertiesByFate->shouldReceive('getStrength')
            ->andReturn($strength);
        $propertiesByFate->shouldReceive('getProperty')
            ->with(PropertyCode::getIt(PropertyCode::AGILITY))
            ->andReturn($agility = new IntegerObject(234));
        $propertiesByFate->shouldReceive('getAgility')
            ->andReturn($agility);
        $propertiesByFate->shouldReceive('getProperty')
            ->with(PropertyCode::getIt(PropertyCode::KNACK))
            ->andReturn($knack = new IntegerObject(345));
        $propertiesByFate->shouldReceive('getKnack')
            ->andReturn($knack);
        $propertiesByFate->shouldReceive('getProperty')
            ->with(PropertyCode::getIt(PropertyCode::WILL))
            ->andReturn($will = new IntegerObject(456));
        $propertiesByFate->shouldReceive('getWill')
            ->andReturn($will);
        $propertiesByFate->shouldReceive('getProperty')
            ->with(PropertyCode::getIt(PropertyCode::INTELLIGENCE))
            ->andReturn($intelligence = new IntegerObject(567));
        $propertiesByFate->shouldReceive('getIntelligence')
            ->andReturn($intelligence);
        $propertiesByFate->shouldReceive('getProperty')
            ->with(PropertyCode::getIt(PropertyCode::CHARISMA))
            ->andReturn($charisma = new IntegerObject(678));
        $propertiesByFate->shouldReceive('getCharisma')
            ->andReturn($charisma);

        return $propertiesByFate;
    }

    /**
     * @return ProfessionLevels|\Mockery\MockInterface
     */
    private function createProfessionLevels()
    {
        $professionLevels = \Mockery::mock(ProfessionLevels::class);
        $professionLevels->shouldReceive('getFirstLevelPropertyModifier')
            ->with(PropertyCode::STRENGTH)
            ->andReturn($strength = 1234);
        $professionLevels->shouldReceive('getFirstLevelStrengthModifier')
            ->andReturn($strength);
        $professionLevels->shouldReceive('getFirstLevelPropertyModifier')
            ->with(PropertyCode::AGILITY)
            ->andReturn($agility = 2345);
        $professionLevels->shouldReceive('getFirstLevelAgilityModifier')
            ->andReturn($agility);
        $professionLevels->shouldReceive('getFirstLevelPropertyModifier')
            ->with(PropertyCode::KNACK)
            ->andReturn($knack = 3456);
        $professionLevels->shouldReceive('getFirstLevelKnackModifier')
            ->andReturn($knack);
        $professionLevels->shouldReceive('getFirstLevelPropertyModifier')
            ->with(PropertyCode::WILL)
            ->andReturn($will = 3456);
        $professionLevels->shouldReceive('getFirstLevelWillModifier')
            ->andReturn($will);
        $professionLevels->shouldReceive('getFirstLevelPropertyModifier')
            ->with(PropertyCode::INTELLIGENCE)
            ->andReturn($intelligence = 5678);
        $professionLevels->shouldReceive('getFirstLevelIntelligenceModifier')
            ->andReturn($intelligence);
        $professionLevels->shouldReceive('getFirstLevelPropertyModifier')
            ->with(PropertyCode::CHARISMA)
            ->andReturn($charisma = 6789);
        $professionLevels->shouldReceive('getFirstLevelPropertyModifier')
            ->andReturn($charisma);

        $professionLevels->shouldReceive('getNextLevelsStrengthModifier')
            ->andReturn(2); // is not limited by FirstLevelProperties and has to fit to wounds table range
        $professionLevels->shouldReceive('getNextLevelsAgilityModifier')
            ->andReturn(23456);
        $professionLevels->shouldReceive('getNextLevelsKnackModifier')
            ->andReturn(34567);
        $professionLevels->shouldReceive('getNextLevelsWillModifier')
            ->andReturn(4); // is not limited by FirstLevelProperties and has to fit to wounds table range
        $professionLevels->shouldReceive('getNextLevelsIntelligenceModifier')
            ->andReturn(56789);
        $professionLevels->shouldReceive('getNextLevelsCharismaModifier')
            ->andReturn(67890);

        $professionLevels->shouldReceive('getFirstLevel')
            ->andReturn($firstLevel = \Mockery::mock(ProfessionLevel::class));
        $firstLevel->shouldReceive('getProfession')
            ->andReturn($profession = \Mockery::mock(Profession::class));
        $profession->shouldReceive('getValue')
            ->andReturn(ProfessionCode::FIGHTER);

        return $professionLevels;
    }
}