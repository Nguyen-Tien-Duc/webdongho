document.addEventListener('DOMContentLoaded',()=>{
    const listImg = document.querySelector('.list-img');
    const imgs = document.querySelectorAll('.list-img img');
    const btnLeft = document.querySelector('.button-left');
    const btnRight = document.querySelector('.button-right');
    const btnSlide = document.querySelector('.btn-slide');
    const length = imgs.length;
    let current = 0;

    // auto change slide after 10s 
    const changeSlide = ()=>{
        if(current== length -1){
            current = 0;
            let width = imgs[0].offsetWidth;
            listImg.style.transform = `translateX(0px)`; 
        }
        else{
            current++;
            let width = imgs[0].offsetWidth;
            listImg.style.transform = `translateX(${width * -1 * current}px)`;              
        }
    }
    let handleEventChangeSlide = setInterval(changeSlide,10000)

    //slide change on button press
    btnRight.addEventListener('click',()=>{
        clearInterval(handleEventChangeSlide);
        changeSlide();
        handleEventChangeSlide =  setInterval(changeSlide,5000);
    })
    btnLeft.addEventListener('click',()=>{
        clearInterval(handleEventChangeSlide);
        if(current== 0){
            current = length -1;
            let width = imgs[0].offsetWidth;
            listImg.style.transform = `translateX(${width * -1 * current}px)`; 
        }
        else{
            current--;
            let width = imgs[0].offsetWidth;
            listImg.style.transform = `translateX(${width * -1 * current}px)`;              
        }
        handleEventChangeSlide =  setInterval(changeSlide,5000);
    })

    //hover img 
    for(let i = 0; i < imgs.length;i++){
        imgs[i].addEventListener('mouseover',()=>{
            btnSlide.classList.toggle('opacity');
        })
    }

},false)