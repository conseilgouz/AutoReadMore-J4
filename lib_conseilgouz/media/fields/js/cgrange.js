/**
* ConseilGouz Custom Field CG Range for Joomla 4.x/5.x/6.x
*
* @author           : ConseilgGouz
* @copyright 		: Copyright (C) 2025 ConseilGouz. All rights reserved.
* @license    		: https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
*/
/* handle CGRange field */
document.addEventListener('DOMContentLoaded', function() {
    let cgranges = document.querySelectorAll('.form-cgrange');
    for(var i=0; i< cgranges.length; i++) {
        cgranges[i].addEventListener('input',function() {
            let $id = this.getAttribute('id');
            range = document.getElementById($id);
            unit = range.getAttribute('unit');
            label = document.querySelector('#cgrange-label-'+$id);
            label.innerHTML = this.value+unit;
        })
    }
    let cgresets = document.querySelectorAll('.cgrange-reset');
    for(var i=0; i< cgresets.length; i++) {
        cgresets[i].addEventListener('click',function() {
            let $id = this.getAttribute('data');
            range = document.getElementById($id);
            unit = range.getAttribute('unit');
            range.value = 0;
            label = document.querySelector('#cgrange-label-'+$id);
            label.innerHTML = 0+unit;
        })
    }
    let cgminus = document.querySelectorAll('.cgrange-minus');
    for(var i=0; i< cgminus.length; i++) {
        cgminus[i].addEventListener('click',function() {
            let $id = this.getAttribute('data');
            range = document.getElementById($id);
            step = parseFloat(range.getAttribute('step'));
            min = parseFloat(range.getAttribute('min'));
            unit = range.getAttribute('unit');
            range.value = parseFloat(range.value) - step;
            if (range.value < min) range.value = min;
            label = document.querySelector('#cgrange-label-'+$id);
            label.innerHTML = range.value+unit;
        })
    }
    let cgplus = document.querySelectorAll('.cgrange-plus');
    for(var i=0; i< cgplus.length; i++) {
        cgplus[i].addEventListener('click',function() {
            let $id = this.getAttribute('data');
            range = document.getElementById($id);
            unit = range.getAttribute('unit');
            step = parseFloat(range.getAttribute('step'));
            max = parseFloat(range.getAttribute('max'));
            range.value = parseFloat(range.value) + parseFloat(step);
            if (range.value > max) range.value = max;
            label = document.querySelector('#cgrange-label-'+$id);
            label.innerHTML = range.value+unit;
        })
    }
    // initialize
    let cglabels = document.querySelectorAll('.cgrange-label');
    for(var i=0; i< cglabels.length; i++) {
        let $id = cglabels[i].getAttribute('data');
        let range = document.querySelector('#'+$id);
        let value = range.getAttribute('value');
        let unit = range.getAttribute('unit');
        cglabels[i].innerHTML = value + unit;
    }

})