import { AuthServiceConfig, SocialLoginModule } from 'angularx-social-login';
import { FacebookLoginProvider, GoogleLoginProvider } from 'angularx-social-login';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { NgbModule, NgbToastModule } from '@ng-bootstrap/ng-bootstrap';

import { AccountComponent } from './components/account/account.component';
import { AddToCartComponent } from './components/shared/add-to-cart/add-to-cart.component';
import { AppComponent } from './app.component';
import { AppHeaderComponent } from './components/layout/app-header/app-header.component';
import { AppRoutingModule } from './app-routing.module';
import { BrowserAnimationsModule } from '@angular/platform-browser/animations';
import { BrowserModule } from '@angular/platform-browser';
import { CheckoutComponent } from './components/checkout/checkout.component';
import { CheckoutNavbarComponent } from './components/checkout/checkout-navbar/checkout-navbar.component';
import { CustomerInfoComponent } from './components/checkout/customer-info/customer-info.component';
import { FiltersComponent } from './components/home/filters/filters.component';
import { HomeComponent } from './components/home/home.component';
import { HttpClientModule } from '@angular/common/http';
import { LayoutComponent } from './components/layout/layout.component';
import { LoginComponent } from './components/login/login.component';
import { MDBBootstrapModule } from 'angular-bootstrap-md';
import { NgModule } from '@angular/core';
import { NgxPayPalModule } from 'ngx-paypal';
import { OrderConfirmationComponent } from './components/order-confirmation/order-confirmation.component';
import { PaginationComponent } from './components/shared/pagination/pagination.component';
import { PaymentInfoComponent } from './components/checkout/payment-info/payment-info.component';
import { PaypalCheckoutComponent } from './components/checkout/payment-info/paypal-checkout/paypal-checkout.component';
import { ProductCardComponent } from './components/home/product-list/product-card/product-card.component';
import { ProductDetailsComponent } from './components/product-details/product-details.component';
import { ProductListComponent } from './components/home/product-list/product-list.component';
import { RegisterComponent } from './components/register/register.component';
import { ReviewComponent } from './components/checkout/review/review.component';
import { ShoppingCartComponent } from './components/shopping-cart/shopping-cart.component';
import { SmallCartComponent } from './components/layout/app-header/small-cart/small-cart.component';
import { ToastrModule } from 'ngx-toastr';

@NgModule({
  declarations: [
    AppComponent,
    LayoutComponent,
    AppHeaderComponent,
    HomeComponent,
    ProductDetailsComponent,
    FiltersComponent,
    ProductListComponent,
    ProductCardComponent,
    PaginationComponent,
    AddToCartComponent,
    ShoppingCartComponent,
    SmallCartComponent,
    LoginComponent,
    RegisterComponent,
    PaypalCheckoutComponent,
    OrderConfirmationComponent,
    AccountComponent,
    CheckoutComponent,
    CheckoutNavbarComponent,
    ReviewComponent,
    CustomerInfoComponent,
    PaymentInfoComponent
  ],
  imports: [
    BrowserModule,
    AppRoutingModule,
    MDBBootstrapModule.forRoot(),
    HttpClientModule,
    FormsModule,
    ReactiveFormsModule,
    BrowserAnimationsModule,
    NgbToastModule,
    ToastrModule.forRoot(),
    NgxPayPalModule,
    SocialLoginModule
  ],
  providers: [
    {
      provide: AuthServiceConfig,
      useFactory: provideConfig
    }
  ],
  bootstrap: [AppComponent]
})
export class AppModule { }
const fbLoginOptions: any = {
  scope: 'pages_messaging,pages_messaging_subscriptions,email,pages_show_list,manage_pages',
  return_scopes: true,
  enable_profile_selector: true
}; // https://developers.facebook.com/docs/reference/javascript/FB.login/v2.11

const googleLoginOptions: any = {
  scope: 'profile email'
}; // https://developers.google.com/api-client-library/javascript/reference/referencedocs#gapiauth2clientconfig

const config = new AuthServiceConfig([
  {
    id: GoogleLoginProvider.PROVIDER_ID,
    provider: new GoogleLoginProvider('263189942868-n5vmdmtk8i0snumqqtnhoels6bg8cqnn.apps.googleusercontent.com', googleLoginOptions)
  },
  {
    id: FacebookLoginProvider.PROVIDER_ID,
    provider: new FacebookLoginProvider('Facebook-App-Id', fbLoginOptions)
  }
]);

export function provideConfig() {
  return config;
}
