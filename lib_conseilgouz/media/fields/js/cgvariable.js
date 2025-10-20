/**
 * CG Variable field for Joomla 4.x/5.x/6.x
 *
 * @author     ConseilgGouz
 * @copyright (C) 2025 www.conseilgouz.com. All Rights Reserved.
 * @license    GNU/GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 */
/* handle CG Variable field */
document.addEventListener('DOMContentLoaded', function() {
    let cgvariables = document.querySelectorAll('.form-cgvariable');
    for(var i=0; i< cgvariables.length; i++) {
        // init 
        bsvar = cgvariables[i].value;
        id = cgvariables[i].getAttribute('id');
        value = cgvariables[i].value;
        check_color(id,value);
        // add listener
        cgvariables[i].addEventListener('input',function() {
            let id = this.getAttribute('id');
            value = this.value;
            check_color(id,value);
        })
    }

})
function check_color(id,value) {
    let cgcolors = document.querySelectorAll('.'+id+'_color');
    let light = document.getElementById(id+'_light');
    let dark = document.getElementById(id+'_dark');
    light.style.backgroundColor = '';
    dark.style.backgroundColor = '';
    bsvar = value;
    let root = document.documentElement;
    let color =  getComputedStyle(root).getPropertyValue(bsvar);
    let bsvar_cassio = bsvar.replace('--bs-','--');
    let color_cassio = getComputedStyle(root).getPropertyValue(bsvar_cassio);
    if (!color && !color_cassio) {
        for(var j=0; j< cgcolors.length; j++) {
            cgcolors[j].style.display = "none";
        }
        return;
    }
    for(var j=0; j< cgcolors.length; j++) {
        cgcolors[j].style.display = "inline-block";
    }
    if (color) {
        light.style.backgroundColor = 'var('+bsvar+')';
        dark.style.backgroundColor = 'var('+bsvar+')';
    } else if (color_cassio) {
        light.style.backgroundColor = 'var('+bsvar_cassio+')';
        dark.style.backgroundColor = 'var('+bsvar_cassio+')';
    }
}