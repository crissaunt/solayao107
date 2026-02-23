<?php 
// home.php
session_start();
include('db_connection.php'); 

if(!isset($_SESSION['user_id']) || !isset($_SESSION['username'])){
    header('Location: login.php');
    exit();
}

// Only regular users (role_id 3) can enter here
$role_id = $_SESSION['role_id'] ?? 3;
if($role_id != 3){
    // Redirect admins/superadmins to their dashboard
    if($role_id == 1 || $role_id == 2){
        header('Location: ../admin/dashboard.php');
        exit();
    }
    // All others back to login
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'] ?? $username;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- clean, flat design with 2px/5px border radius, no hover effects -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
        }

        body {
            background-color: #f2f7f0;
            color: #1e3a2f;
            line-height: 1.5;
        }

        /* header & navigation – flat, no hover background shifts */
        .header {
            background: #ffffff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 0.8rem 2rem;
            border-bottom: 1px solid #bdd8b3;
        }

        .header .flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo {
            font-size: 2rem;
            font-weight: 600;
            letter-spacing: 1px;
            color: #2b5e3c;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .navbar ul {
            list-style: none;
            display: flex;
            gap: 1.2rem;
        }

        .navbar ul li {
            position: relative;
        }

        .navbar ul li a {
            text-decoration: none;
            font-weight: 550;
            color: #1d4d2d;
            padding: 0.5rem 0.8rem;
            border-radius: 2px;        /* 2px radius */
            font-size: 1.15rem;
            display: inline-block;
        }

        /* dropdown – flat, no hover background */
        .navbar ul ul {
            display: none;
            position: absolute;
            top: 2.2rem;
            left: 0;
            background: white;
            border-radius: 2px;          /* 2px radius */
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: 0.6rem 0;
            min-width: 140px;
            flex-direction: column;
            gap: 0.3rem;
            border: 1px solid #cfebc2;
            z-index: 1000;
        }

        .navbar ul li:hover > ul {
            display: flex;
        }

        .navbar ul ul li {
            width: 100%;
        }

        .navbar ul ul a {
            display: block;
            padding: 0.6rem 1.6rem;
            border-radius: 0;
            background: none;
            font-weight: 500;
        }

        /* icons & button-37 style – flat, 2px radius */
        .icons {
            display: flex;
            align-items: center;
            gap: 1.2rem;
        }

        .icons .fa-search {
            font-size: 1.6rem;
            color: #296d3e;
        }

        .button-37 {
            background-color: #2a6e3b;
            border: 1px solid #1c4c2a;
            border-radius: 2px;           /* 2px radius */
            color: #fff;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 550;
            padding: 0.5rem 1.6rem;
            text-align: center;
            display: inline-block;
            transition: none;              /* no hover transition */
            text-decoration: none;
        }

        /* Logout button style - slightly different color to distinguish */
        .button-logout {
            background-color: #a13d3d;
            border: 1px solid #6b2b2b;
        }

        /* User greeting style */
        .user-greeting {
            font-size: 1rem;
            font-weight: 500;
            color: #1d4d2d;
            background: #e8f3e3;
            padding: 0.5rem 1.2rem;
            border-radius: 2px;
            border: 1px solid #bdd8b3;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-greeting i {
            color: #2b5e3c;
        }

        /* home section – 2px radius bottom */
        .home {
            background: linear-gradient(97deg, #d8efd0 0%, #f3fcf0 60%);
            min-height: 480px;
            display: flex;
            align-items: center;
            padding: 2rem 6%;
            border-radius: 0 0 2px 2px;   /* 2px bottom radius */
            margin-bottom: 2.5rem;
        }

        .home .content {
            max-width: 700px;
        }

        .home h3 {
            font-size: 3.6rem;
            font-weight: 700;
            color: #1c4c29;
            line-height: 1.2;
            margin-bottom: 1rem;
        }

        .home p {
            font-size: 1.35rem;
            color: #295f34;
            margin-bottom: 2rem;
        }

        .btn .button-37 {
            font-size: 1.3rem;
            padding: 0.8rem 2.5rem;
            background-color: #235f33;
        }

        /* products section */
        .products {
            padding: 2rem 6%;
            max-width: 1500px;
            margin: 0 auto;
        }

        .title {
            font-size: 2.6rem;
            font-weight: 600;
            color: #1c4927;
            margin-bottom: 2.8rem;
            border-left: 12px solid #509c5b;
            padding-left: 1.5rem;
        }

        .box-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2.2rem;
            margin-bottom: 3.5rem;
        }

        .box {
            background: #fafff9;
            border-radius: 5px;            /* 5px radius */
            padding: 2rem 1.5rem 1.8rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border: 1px solid #cbe6bf;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: none;               /* no transform */
        }

        .price {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0f4d1f;
            background: #daf3d1;
            display: inline-block;
            padding: 0.2rem 1.2rem;
            border-radius: 2px;             /* 2px radius */
            margin-bottom: 1rem;
            border: 1px solid #a5cf9b;
            width: fit-content;
        }

        .image {
            width: 180px;
            height: 180px;
            object-fit: cover;
            border-radius: 2px;              /* 2px radius – square but slight */
            border: 5px solid #c4e2b7;
            margin: 0.5rem 0 1rem;
            background: #e0f0d6;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .name {
            font-size: 1.8rem;
            font-weight: 600;
            color: #194d26;
            margin-bottom: 1rem;
        }

        .qty {
            width: 90px;
            padding: 0.5rem;
            margin: 0.7rem 0 1.2rem;
            border: 2px solid #afcfaa;
            border-radius: 2px;               /* 2px radius */
            text-align: center;
            font-size: 1.1rem;
            font-weight: 550;
            background: white;
            color: #15421f;
        }

        .option-btn {
            background: #fff;
            border: 2px solid #589065;
            color: #1b572b;
            font-size: 1rem;
            font-weight: 600;
            padding: 0.65rem 1.2rem;
            margin: 0.25rem 0;
            border-radius: 2px;                /* 2px radius */
            cursor: pointer;
            width: 100%;
            max-width: 200px;
            transition: none;                   /* no hover */
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .box .option-btn {
            margin-top: 6px;
        }

        .more-btn {
            display: flex;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .more-btn .option-btn {
            width: auto;
            padding: 0.9rem 3.5rem;
            font-size: 1.3rem;
            background: #ebf9e5;
            border-width: 3px;
        }

        /* responsive – keep same, no hover */
        @media (max-width: 800px) {
            .header .flex {
                flex-direction: column;
                gap: 0.7rem;
            }
            .navbar ul {
                flex-wrap: wrap;
                justify-content: center;
            }
            .home h3 {
                font-size: 2.8rem;
            }
        }

        @media (max-width: 520px) {
            .box-container {
                grid-template-columns: 1fr;
            }
            .home {
                min-height: 380px;
            }
        }

        .logo i {
            font-style: normal;
        }

        .icons .button-37 {
            padding: 0.5rem 1.5rem;
            margin-left: 0.2rem;
        }

        .navbar ul li {
            z-index: 1000;
        }

        input[type=number]::-webkit-inner-spin-button {
            opacity: 1;
            height: 22px;
        }

        /* ensure no hover transforms or background shifts on any element */
        a:hover, button:hover, .button-37:hover, .option-btn:hover, .navbar ul li a:hover {
            background-color: transparent;   /* remove any hover background */
            transform: none;
            box-shadow: none;
            color: inherit;                  /* keep original color */
        }

        /* specific override for buttons – keep original background */
        .button-37:hover {
            background-color: #2a6e3b;       /* same as normal – no change */
            border-color: #1c4c2a;
            transform: none;
            box-shadow: none;
        }

        .button-logout:hover {
            background-color: #a13d3d;       /* same as normal – no change */
            border-color: #6b2b2b;
        }

        .option-btn:hover {
            background: #fff;                 /* stay white, no hover effect */
            border-color: #589065;
            color: #1b572b;
        }

        /* dropdown items – no hover background */
        .navbar ul ul a:hover {
            background: transparent;
        }

        /* search icon – no scale */
        .icons .fa-search:hover {
            transform: none;
            color: #296d3e;
        }
    </style>
    <title>greenRoots | plant collection</title>
</head>
<body>
    <header class="header">
        <div class="flex">
            <a href="#" class="logo">PLants.🌿</a>

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
                    <li><a href="#">Account</a>
                        <ul>
                            <li><a href="#">Profile</a></li>
                            <li><a href="logout.php">Logout</a></li>
                        </ul>
                    </li>      
                </ul>    
            </nav>    

            <div class="icons">
                <span class="user-greeting">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($full_name); ?>
                </span>
                <a href="#" class="fas fa-search"></a>
                <a href="logout.php"><button class="button-37 button-logout">Logout</button></a>
            </div>
        </div>
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
            <!-- product card 1 -->
            <form action="#" class="box">
                <div class="price">$12/-</div>
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='45' fill='%23669977' /%3E%3Cpath d='M28 42 Q 40 20 55 30 Q 70 40 65 58 Q 60 75 42 72 Q 25 68 28 42' fill='%2331663b' /%3E%3Ccircle cx='40' cy='45' r='4' fill='%23f4d03f' /%3E%3Ccircle cx='60' cy='50' r='4' fill='%23f4d03f' /%3E%3C/svg%3E"
                     alt="Monstera" class="image">
                <div class="name">Monstera</div>
                <input type="number" name="quantity-of-product" value="1" min="0" class="qty">
                <input type="submit" value="Add to Wishlist" class="option-btn">
                <input type="submit" value="Add to Cart" class="option-btn">
            </form>   

            <!-- product card 2 -->
            <form action="#" class="box">
                <div class="price">$18/-</div>
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='55' r='30' fill='%233f7844' /%3E%3Crect x='45' y='15' width='10' height='35' fill='%238b5a2b' /%3E%3Ccircle cx='28' cy='40' r='7' fill='%23e67e22' /%3E%3Ccircle cx='72' cy='40' r='7' fill='%23e67e22' /%3E%3C/svg%3E"
                     alt="Ficus" class="image">
                <div class="name">Ficus</div>
                <input type="number" name="quantity-of-product" value="1" min="0" class="qty">
                <input type="submit" value="Add to Wishlist" class="option-btn">
                <input type="submit" value="Add to Cart" class="option-btn">
            </form>   

            <!-- product card 3 -->
            <form action="#" class="box">
                <div class="price">$14/-</div>
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='60' r='35' fill='%235a945b' /%3E%3Cpath d='M50 20 L42 40 L58 40 Z' fill='%2399bb77' /%3E%3Ccircle cx='30' cy='55' r='6' fill='%23f1c40f' /%3E%3Ccircle cx='70' cy='55' r='6' fill='%23f1c40f' /%3E%3C/svg%3E"
                     alt="Pilea" class="image">
                <div class="name">Pilea</div>
                <input type="number" name="quantity-of-product" value="1" min="0" class="qty">
                <input type="submit" value="Add to Wishlist" class="option-btn">
                <input type="submit" value="Add to Cart" class="option-btn">
            </form>   

            <!-- product card 4 -->
            <form action="#" class="box">
                <div class="price">$21/-</div>
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='58' r='33' fill='%2338733e' /%3E%3Cpolygon points='50,20 35,45 65,45' fill='%23338844' /%3E%3Ccircle cx='35' cy='63' r='6' fill='%23d4ac0d' /%3E%3Ccircle cx='65' cy='63' r='6' fill='%23d4ac0d' /%3E%3C/svg%3E"
                     alt="Alocasia" class="image">
                <div class="name">Alocasia</div>
                <input type="number" name="quantity-of-product" value="1" min="0" class="qty">
                <input type="submit" value="Add to Wishlist" class="option-btn">
                <input type="submit" value="Add to Cart" class="option-btn">
            </form>   

            <!-- product card 5 -->
            <form action="#" class="box">
                <div class="price">$16/-</div>
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='40' fill='%2342852e' /%3E%3Cpath d='M32 32 L46 24 L58 34 L70 28 L68 48 L52 60 L34 56 L30 40 Z' fill='%23226b2a' /%3E%3C/svg%3E"
                     alt="Fern" class="image">
                <div class="name">Bird’s Nest Fern</div>
                <input type="number" name="quantity-of-product" value="1" min="0" class="qty">
                <input type="submit" value="Add to Wishlist" class="option-btn">
                <input type="submit" value="Add to Cart" class="option-btn">
            </form>  
        </div>

        <div class="more-btn">
            <a href="#" class="option-btn">load more</a>
        </div>
    </section>

    <script>
        console.log('🌱 flat design, 2px/5px radius, no hover');
    </script>
</body>
</html>