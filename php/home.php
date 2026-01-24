<?php 

include('db_connection.php'); 

if(!isset($_SESSION['username'])){
    header('Location: ../html/login.html');

}



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">

    <title>Home</title>
</head>
<body>
    <header class="header">

        <div class="flex">
            <a href="#" class="logo">PLants.&#127804;</a>

              <nav class="navbar">
                <ul>
                    <li><a href="#">Browse</a></li>
                    <li><a href="#">Pages</a>
                        <ul>
                            <li><a href="#">About</a></li>
                            <li><a href="#">Contact</a></li>
                        </ul>
                    </li>  
                    <li><a href="#">Shop</a></li>
                    <li><a href="#">Orders</a></li>
                    <li><a href=#>Account</a>
                        <ul>
                            <li><a href="#">Profile</a></li>
                            <li><a href="logout.php">Logout</a></li>
                        </ul>
                    </li>      
                </ul>    
        </nav>    


        <div class="icons">
            <!-- search -->
            <a href="#" class="fas fa-search"></a>
            <!-- profile logo -->
            
            <a href="logout.php"><button class="button-37"> Logout</button><span></span></a>
        
    </header>
   
    <section class="home">
        <div class="content">
            <h3>Collect Plants</h3>
            <p>Plants are the breath of life, nourishing the Earth and every living being upon it.</p>
            <a href="#" class="btn"><button class="button-37" role="button">Discover More</button></a>

        </div>

    </section>

    <section class="products">
        <h1 class="title">Latest Products</h1>

    
            <div class="box-container">

            <form action=""  class="box">
                <!-- details -->
               

                <div class="price">$12/-</div>
                <img src="../product/plantttt.jpg" alt="" class="image">
                <div class="name">PLant</div>

                <!-- type/input of quantity -->
                <input type="number" name="quantity-of-product" value="1" min="0" class="qty">

                <input type="submit" value="Add to Wishlist" class="option-btn">
                <input type="submit"  value="Add to Cart" class="option-btn">
            </form>   

            <form action=""  class="box">
                
                <div class="price">$12/-</div>
                <img src="../product/plantttt.jpg" alt="" class="image">
                <div class="name">PLant</div>

                <!-- type/input of quantity -->
                <input type="number" name="quantity-of-product" value="1" min="0" class="qty">

                <input type="submit" value="Add to Wishlist" class="option-btn">
                <input type="submit"  value="Add to Cart" class="option-btn">
            </form>   


            <form action=""  class="box">
                <!-- details -->
              

                <div class="price">$12/-</div>
                <img src="./.product/plantttt.jpg" alt="" class="image">
                <div class="name">PLant</div>

                <!-- type/input of quantity -->
                <input type="number" name="quantity-of-product" value="1" min="0" class="qty">

                <input type="submit" value="Add to Wishlist" class="option-btn">
                <input type="submit"  value="Add to Cart" class="option-btn">
            </form>   


            <form action=""  class="box">
                <!-- details -->
              

                <div class="price">$12/-</div>
                <img src="../product/plantttt.jpg" alt="" class="image">
                <div class="name">PLant</div>

                <!-- type/input of quantity -->
                <input type="number" name="quantity-of-product" value="1" min="0" class="qty">

                <input type="submit" value="Add to Wishlist" class="option-btn">
                <input type="submit"  value="Add to Cart" class="option-btn">
            </form>  
            
            
            <form action=""  class="box">
                <!-- details -->
              

                <div class="price">$12/-</div>
                <img src="../product/plantttt.jpg" alt="" class="image">
                <div class="name">PLant</div>

                <!-- type/input of quantity -->
                <input type="number" name="quantity-of-product" value="1" min="0" class="qty">

                <input type="submit" value="Add to Wishlist" class="option-btn">
                <input type="submit"  value="Add to Cart" class="option-btn">
            </form>  

        </div>

        <div class="more-btn">
            <a href="#" class="option-btn">load more</a>

        </div>

    </section>



    <script>
  // Push the current state onto the history stack

</script>

    
</body>
</html>