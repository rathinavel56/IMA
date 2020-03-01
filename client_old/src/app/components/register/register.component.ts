import { AuthService, FacebookLoginProvider, GoogleLoginProvider, SocialUser } from 'angularx-social-login';
import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';

import { Customer } from 'src/app/models/customer';
import { CustomerService } from 'src/app/services/Customer/customer.service';
import { Router } from '@angular/router';

@Component({
  selector: 'app-register',
  templateUrl: './register.component.html',
  styleUrls: ['./register.component.scss']
})
export class RegisterComponent implements OnInit {

  customer: Customer = new Customer();
  registerForm: FormGroup;
  loading = false;
  submitted = false;
  private user: SocialUser;

  constructor(private formBuilder: FormBuilder,
              private customerService: CustomerService,
              private router: Router,
              private authService: AuthService) { }

  ngOnInit() {
    this.authService.authState.subscribe((user) => {
      console.log('1-----', user);
      this.user = user;
    });
    this.registerForm = this.formBuilder.group({
      email: ['', Validators.required],
      username: ['', Validators.required],
      password: ['', [Validators.required, Validators.minLength(6)]],
      is_agree_terms_conditions: [true]
    });
  }

  get f() { return this.registerForm.controls; }

  signInWithGoogle(): void {
    this.authService.signIn(GoogleLoginProvider.PROVIDER_ID);
  }

  signInWithFB(): void {
    this.authService.signIn(FacebookLoginProvider.PROVIDER_ID);
  }

  signOut(): void {
    this.authService.signOut();
  }

  onSubmit() {
    this.submitted = true;
    if (this.registerForm.invalid) {
      return;
    }
    this.customer = this.registerForm.value;
    this.customerService.Register(this.customer)
      .subscribe(data => {
        if (data) {
          localStorage.setItem('user', JSON.stringify(data));
          this.router.navigate(['/products']);
        }
      });
  }

}
