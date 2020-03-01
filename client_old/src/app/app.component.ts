import { Component } from '@angular/core';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.scss']
})
export class AppComponent {
  title = 'frontend';
  constructor(){
    // const serverURL = 'https://turing-backend-v2.herokuapp.com/api/';
    const serverURL = 'http://ec2-18-223-28-89.us-east-2.compute.amazonaws.com/api/v1/';
    localStorage.setItem('ServerUrl', serverURL);
  }
}
