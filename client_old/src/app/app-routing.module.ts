import { RouterModule, Routes } from '@angular/router';

import { AccountComponent } from './components/account/account.component';
import { CheckoutComponent } from './components/checkout/checkout.component';
import { CustomerInfoComponent } from './components/checkout/customer-info/customer-info.component';
import { HomeComponent } from './components/home/home.component';
import { LayoutComponent } from './components/layout/layout.component';
import { LoginComponent } from './components/login/login.component';
import { NgModule } from '@angular/core';
import { OrderConfirmationComponent } from './components/order-confirmation/order-confirmation.component';
import { PaymentInfoComponent } from './components/checkout/payment-info/payment-info.component';
import { ProductDetailsComponent } from './components/product-details/product-details.component';
import { RegisterComponent } from './components/register/register.component';
import { ReviewComponent } from './components/checkout/review/review.component';
import { ShoppingCartComponent } from './components/shopping-cart/shopping-cart.component';

const routes: Routes = [
  // App Routes goes here
  {
    path: 'products',
    component: LayoutComponent,
    children: [
      { path: '', component: HomeComponent },
      { path: 'product-details/:id', component: ProductDetailsComponent },
    ]
  },
  { path: 'cart/shopping-cart', component: ShoppingCartComponent},
  { path: 'order/order-confirmation', component: OrderConfirmationComponent},
  { path: 'customer/my-account', component: AccountComponent},
  { path: 'login', component: LoginComponent},
  { path: 'register', component: RegisterComponent},
  { path: 'checkout', component: CheckoutComponent, data: {title: 'Check out'},
    children: [
      { path: 'review',  component: ReviewComponent,  data: {title: 'Order Review'} },
      { path: 'customer-information', component: CustomerInfoComponent,  data: {title: 'Customer Information'} },
      { path: 'payment-information', component: PaymentInfoComponent,  data: {title: 'Payment Information'} },
    ]
  },
   // otherwise redirect to home
   { path: '**', redirectTo: 'login' }
];
@NgModule({
  imports: [RouterModule.forRoot(routes)],
  exports: [RouterModule]
})
export class AppRoutingModule { }
