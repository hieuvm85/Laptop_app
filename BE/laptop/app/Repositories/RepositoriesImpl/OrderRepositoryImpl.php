<?php
namespace App\Repositories\RepositoriesImpl;

use App\Exceptions\CustomQuantityException;
use App\Models\Address;
use App\Models\Laptop;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use App\Repositories\OrderRepository;
use Exception;
use Illuminate\Support\Facades\DB;

class OrderRepositoryImpl implements OrderRepository{

    private $addressRepository;
    
    
    public function __construct(){

        $this->addressRepository  = new AddressRepositoryImpl();
    }

    public function createOrder($data){
        DB::beginTransaction();
        try{
            // lay ra dia chi neu chua co thi to dia chi moi
            $addressReq = $data['address'];


            $address = $this->addressRepository->createOrGetAddress( $addressReq);


            // lay ra user neu chua co thi tao user moi voi password = ""
            $userRe= $data['user'];

            $user = User::where('phone', $userRe['phone'])->first();

            if(!$user){
                $user= new User();
                $user->name = $userRe['name'];
                $user->password = "";
                $user->phone = $userRe['phone'];
                $user->email = $userRe['phone']."@gmail.com";
                
            }   
            if(!empty($user->address_id)) {
                $user->address_id = $address->id;
            } 
            // co the cho vao if o ben tren de user co roi thi khong can phia luu lai
            $user->save();

            
            // tao mot order moi
            $order = new Order();
            $order->user_id = $user->id;
            $order->address_id = $address->id;
            $order->note = $data['note'] || "*";
            // set tam trang thai thanh toan la PENDING
            $order->status = 'PENDING';
            $order->amount=0;
            $order->save();


            $carts = $data['carts'];
            $amount=0;
            foreach($carts as $cart){
                $orderDetail =  new OrderDetail();

                $orderDetail->laptop_id = $cart['id'];
                $orderDetail->user_id = $user->id;
                $orderDetail->order_id = $order->id;
                $orderDetail->quantity= $cart['quantity'];
                $orderDetail->status= true;

                $laptop = Laptop::find($cart['id']);
                if($laptop->quantity <$cart['quantity'] ){
                    throw  new CustomQuantityException("Over quantity limit");
                }
                $laptop->quantity -=  $cart['quantity'];
                $amount += $orderDetail->price_sold * $orderDetail->quantity;
                $orderDetail->price_sold= $laptop->sale_price;

               
                $orderDetail->save();
            }
            $order->amount=$amount;
            $order->save();


            DB::commit();
            return $order;
        }
        catch(Exception $e){
            DB::rollBack();
            throw $e;
        }
       
    }


    public function getOrderUser($idUser,$status){
        $orderQuery = Order::with(['orderDetails.laptop'])
                ->where('user_id',$idUser);

        if($status!="ALL") {
            $orderQuery = $orderQuery->where('status',$status);
        }    
        $order= $orderQuery->orderBy('id', 'desc')->get();      
        return $order;
    }
}