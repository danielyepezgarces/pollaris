<?php
namespace App\Form\Type;

use App\Entity\PollCohost;
use App\Form\Transformer\UserToUsernameTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

class PollCohostType extends AbstractType
{
    public function __construct(
        private UserToUsernameTransformer $userToUsernameTransformer,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('user', TextType::class, [
            'required' => false,
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

        $builder->get('user')->addModelTransformer($this->userToUsernameTransformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PollCohost::class,
        ]);
    }
}
