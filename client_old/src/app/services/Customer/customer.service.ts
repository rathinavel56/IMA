import { Customer } from 'src/app/models/customer';
import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class CustomerService {

  url = localStorage.getItem('ServerUrl');
  constructor(private http: HttpClient) { }

  Register(customer: Customer): Observable<boolean> {
    return this.http.post<boolean>(`${this.url}users/register`, customer);
  }

  Login(customer: Customer): Observable<Customer> {
    return this.http.post<Customer>(`${this.url}users/login`, customer);
  }

  Logout() {
    const result = this.http.get(`${this.url}users/logout`);
    return result;
  }

}
