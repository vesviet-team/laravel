<?php

namespace App\Filament\Seller\Pages\Auth;

use App\Actions\RegisterSellerAction;
use App\Exceptions\SellerActionException;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Seller Registration page for the Filament Seller Panel.
 *
 * SF-03 fix: The entire registration (User + SellerProfile + SellerPage) is wrapped
 * in a single atomic transaction. If SellerProfile creation fails, the User record
 * is rolled back — no orphan users are left in the database.
 *
 * Flow:
 *   1. Filament validates the form (Step 1: account, Step 2: shop details)
 *   2. handleRegistration() opens an outer transaction
 *   3. Inside the tx: parent creates User, then RegisterSellerAction creates SellerProfile + Page
 *   4. If any step fails, the entire tx rolls back
 *
 * ADR-S2: Outer transaction boundary is declared here because Filament's
 *         parent::handleRegistration() cannot be called from inside an Action.
 *         This is the minimum-footprint exception to ADR-S2.
 * ADR-S3 Trust Zone: Restricted — any changes require human review.
 */
class Register extends BaseRegister
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Tài khoản')
                        ->description('Thông tin đăng nhập')
                        ->schema([
                            $this->getNameFormComponent(),
                            $this->getEmailFormComponent(),
                            $this->getPasswordFormComponent(),
                            $this->getPasswordConfirmationFormComponent(),
                        ]),
                    Wizard\Step::make('Cửa hàng')
                        ->description('Thiết lập gian hàng')
                        ->schema([
                            TextInput::make('shop_name')
                                ->label('Tên Shop')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->label('Số điện thoại liên hệ')
                                ->tel()
                                ->required()
                                ->maxLength(20),
                            Select::make('industry')
                                ->label('Ngành hàng')
                                ->options([
                                    'fashion' => 'Thời trang & Phụ kiện',
                                    'food' => 'Ẩm thực & Đồ uống',
                                    'cosmetics' => 'Mỹ phẩm & Làm đẹp',
                                    'general' => 'Khác',
                                ])
                                ->required(),
                        ]),
                ])->submitAction(new HtmlString('<button type="submit" class="filament-button">Hoàn tất đăng ký</button>')),
            ])->statePath('data');
    }

    /**
     * Handle the full registration within a single atomic transaction.
     *
     * SF-03: Wraps both User creation (via parent) and SellerProfile+Page creation
     * (via RegisterSellerAction) in one transaction. A failure in either step rolls
     * back all changes, preventing orphan User records with no SellerProfile.
     *
     * Note: RegisterSellerAction opens its own nested transaction internally.
     * In MySQL with InnoDB, nested transactions are handled via SAVEPOINTs — the inner
     * commit only promotes to real commit when the outer transaction commits.
     */
    protected function handleRegistration(array $data): Model
    {
        try {
            return DB::transaction(function () use ($data) { // ADR-S2: exception allowed — parent::handleRegistration() cannot be called from within an Action
                // Step 1: Create the User (delegates to Filament's base registration).
                $user = parent::handleRegistration($data);

                // Step 2: Create SellerProfile + SellerPage for this User.
                // RegisterSellerAction::execute() expects an explicit User instance (SF-09).
                app(RegisterSellerAction::class)->execute($user, [
                    'shop_name' => $data['shop_name'],
                    'phone' => $data['phone'],
                    'email' => $user->email, // Use User's email, not form data (Wizard may not expose it in $data)
                ]);

                return $user;
            });
        } catch (SellerActionException $e) {
            Notification::make()
                ->title('Đăng ký thất bại')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        } catch (Throwable $e) {
            Notification::make()
                ->title('Lỗi hệ thống')
                ->body('Đã xảy ra lỗi trong quá trình đăng ký. Vui lòng thử lại.')
                ->danger()
                ->send();

            throw $e;
        }
    }
}
