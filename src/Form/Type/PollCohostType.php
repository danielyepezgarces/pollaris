<?php
namespace App\Form\Type;

use App\Entity\PollCohost;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class PollCohostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('user', EntityType::class, [
            'class' => User::class,
            'choice_label' => 'username',
            'label' => new TranslatableMessage('forms.poll_cohost_type.user.label'),
        ]);
        $builder->add('right', ChoiceType::class, [
            'choices' => [
                'forms.poll_cohost_type.right.edit' => 'edit',
                'forms.poll_cohost_type.right.full' => 'full',
            ],
            'choice_translation_domain' => 'messages',
            'label' => new TranslatableMessage('forms.poll_cohost_type.right.label'),
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PollCohost::class,
        ]);
    }
}
