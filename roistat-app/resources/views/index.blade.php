<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
    <form id="form" >
        @csrf
        <input required placeholder="Имя" type="text" id="name" class="form_fields">
        <input required placeholder="Почта" type="email" id="email" class="form_fields">
        <input required placeholder="Телефон" type="text" id="phone" class="form_fields">
        <input required placeholder="Цена" type="text" id="price" class="form_fields">

        <button type="submit" id="submit">Отправить</button>
    </form>

    <script src="/assets/jquery.js"></script>
    <script>
        let time = 0;
        setTimeout(()=>{
            time = 1;
        }, 30000)

        $('#form').on('submit',function(event){
            event.preventDefault();

            let name = $('#name').val();
            let email = $('#email').val();
            let phone = $('#phone').val();
            let price = $('#price').val();

            $.ajax({
                url: "{{route('formHandler')}}",
                headers: "Content-Type: application/json",
                type:"POST",
                data:{
                    "_token": "{{ csrf_token() }}",
                    name:name,
                    email:email,
                    phone:phone,
                    price:price,
                    time:time
                },
                success:function(response){
                    console.log(response);
                },
            });
        })
    </script>

</body>
</html>
