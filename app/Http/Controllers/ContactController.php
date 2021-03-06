<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Input;
use App\Http\Models\Contact;
use App\Http\Models\Group;
use App\Http\Models\Users;
use App\Http\Models\GroupContact;
use App\Http\Models\Reservation;
use Illuminate\Http\Request;
use App\Http\Requests\Contact\CreateRequest;
use App\Http\Requests\Contact\CreateWriteRequest;
use App\Http\Services\VerificationService;

use JWTAuth;
use Validator;

class ContactController extends BaseController
{

	

    /**
     * Create a new instance of ContactController class
     *
     * @return void
     */
    public function __construct()
    {
        $this->verificationRepo = new VerificationService();
    }


    /**
     *
     * @OA\Get(
     *   path="/api/auth/contact",
     *   summary="List of contacts",
     *   operationId="index",   
     *   tags={"Contacts"},     
     *   @OA\Response(
     *      response=200,
     *      ref="../Swagger/definitions.yaml#/components/responses/Success"
     *    ),
     *   @OA\Response(
     *      response=401,
     *      ref="../Swagger/definitions.yaml#/components/responses/Unauthorized"
     *    ),
     *   @OA\Response(
     *      response=500,
     *      ref="../Swagger/definitions.yaml#/components/responses/InternalServerError"
     *   ),
     *  )
     */
    public function index()
    {
        
        $contactos = Contact::with('usuario')->paginate(15);

        return $this->sendResponse($contactos->toArray(), 'Contactos devueltos con éxito');
    }


    /**
     *
     * @OA\Post(
     *   path="/api/auth/store_write",
     *   summary="create contact Just Numbers",
     *   operationId="store_write",   
     *   tags={"Contacts"},
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/phone"
     *    ),    
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/array_group"
     *    ),   
     *   @OA\Response(
     *      response=200,
     *      ref="../Swagger/definitions.yaml#/components/responses/Success"
     *    ),
     *   @OA\Response(
     *      response=401,
     *      ref="../Swagger/definitions.yaml#/components/responses/Unauthorized"
     *    ),
     *   @OA\Response(
     *      response=500,
     *      ref="../Swagger/definitions.yaml#/components/responses/InternalServerError"
     *   ),
     *  )
     */
    public function store_write(CreateWriteRequest $request)
    {
        
        $user_now = JWTAuth::parseToken()->authenticate(); 
        $phones = $request->input('phone');
        $groups = $request->input('array_group');
        $addGroup = false;
        if($request->input('array_group') != null && sizeof($request->input('array_group')) > 0){
            for ($i=0; $i < sizeof($groups); $i++) { 
                $group = Group::find($groups[$i]);
                if (is_null($group)) {
                    return $this->sendError('No existe el Id: '.$groups[$i].' en la tabla Group.');
                }
            }
        }
        $arrayContact = array();
        foreach ($phones as $phone) {            
            $contact_exist = Contact::where('phone', $phone)->where('user_email', $user_now->email)->first();

            if (!$contact_exist) {

                $phonenumber = $this->verificationRepo->verifyPhonenumbers($phone);
                if(!$phonenumber){
                    return $this->sendError('Número no válido: '.$phone);
                }

                $contactos= new Contact();
                $contactos->phone = $phone;
                $contactos->user_email = $user_now->email;
                $contactos->status = 1;
                $contactos->save();   

                array_push($arrayContact, $contactos->toArray());              
                
                if($request->input('array_group') != null && sizeof($request->input('array_group')) > 0){
                    for ($i=0; $i < sizeof($groups); $i++) { 
                        $group_contacto = new GroupContact();
                        $group_contacto->id_contact = $contactos->id;
                        $group_contacto->id_group = $groups[$i];
                        $group_contacto->save();
                    }
                    $addGroup = true;
                }

            }else if($request->input('array_group') != null && sizeof($request->input('array_group')) > 0){
                
                for ($i=0; $i < sizeof($groups); $i++) { 
                    
                    $group_contacto = GroupContact::where('id_contact', $contact_exist->id)->where('id_group', $groups[$i])->first();
                    if(!$group_contacto){
                        $group_contacto = new GroupContact();
                        $group_contacto->id_contact = $contact_exist->id;
                        $group_contacto->id_group = $groups[$i];
                        $group_contacto->save();
                    }
                    
                }
                array_push($arrayContact, $contact_exist->toArray()); 
                return $this->sendResponse($arrayContact, 'Contactos ya registrados, se asignaron a los grupos correspondientes.');

            }else{
                return $this->sendError('Los contactos ya se encuentran registrados');
            }
        }
        
        if ($addGroup) {
            $contactos_groups = GroupContact::with('contacto')->with('grupo')->whereIn('id_group', $groups)->get();
            return $this->sendResponse($contactos_groups->toArray(), 'Contactos creados y asociados a los grupo(s) con éxito');
        }else{
            return $this->sendResponse($arrayContact, 'Contactos creado con éxito');
        }
        

        
    }
    


    /**
     *
     * @OA\Post(
     *   path="/api/auth/contact",
     *   summary="create a specific contact",
     *   operationId="store",   
     *   tags={"Contacts"},      
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/email_contact"
     *    ), 
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/phone"
     *    ),
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/first_name"
     *    ),
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/last_name"
     *    ),
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/born_date"
     *    ), 
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/date_reservation"
     *    ), 
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/gender"
     *    ), 
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/status"
     *    ),
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/array_group"
     *    ),   
     *   @OA\Response(
     *      response=200,
     *      ref="../Swagger/definitions.yaml#/components/responses/Success"
     *    ),
     *   @OA\Response(
     *      response=401,
     *      ref="../Swagger/definitions.yaml#/components/responses/Unauthorized"
     *    ),
     *   @OA\Response(
     *      response=500,
     *      ref="../Swagger/definitions.yaml#/components/responses/InternalServerError"
     *   ),
     *  )
     */
    public function store(CreateRequest $request)
    {
       
        $user_now = JWTAuth::parseToken()->authenticate();        
        
        $contact_exist = Contact::where('phone', $request->input('phone'))->first();
        if ($contact_exist) {

            if($request->input('date_reservation') != null){
                $reser_contact = new Reservation();
                $reser_contact->reservation_date = $request->input('date_reservation');
                $reser_contact->id_contact = $contact_exist->id;
                $reser_contact->save();
                return $this->sendResponse($contactos->toArray(), 'El contacto ya existe, se ha registrado la fecha de reservación con éxito');
            }else{
                return $this->sendResponse($contact_exist->toArray(), "Contacto ya existe");
            }
            
        }else{


            if($request->input('array_group') != null && sizeof($request->input('array_group')) > 0){
                $groups = $request->input('array_group');

                for ($i=0; $i < sizeof($groups); $i++) { 
                    $group = Group::find($groups[$i]);
                    if (is_null($group)) {
                        return $this->sendError('No existe el Id: '.$groups[$i].' en la tabla Group.');
                    }
                }
            }

            $phonenumber = $this->verificationRepo->verifyPhonenumbers($request->input('phone'));
            if(!$phonenumber){
                return $this->sendError('Número no válido: '.$request->input('phone'));
            }

            $contactos= new Contact();
            $contactos->email = $request->input('email_contact');
            $contactos->phone = $request->input('phone');
            $contactos->first_name =$request->input('first_name');
            $contactos->last_name = $request->input('last_name');
            $contactos->born_date = $request->input('born_date');            
            $contactos->status = $request->input('status');
            $contactos->user_email = $user_now->email;
            
            if($request->input('gender') != null){
                $contactos->gender = $request->input('gender');
            }

            $contactos->save();

            $c_id = $contactos->id; 



            if($request->input('date_reservation') != null){
                $reser_contact = new Reservation();
                $reser_contact->reservation_date = $request->input('date_reservation');
                $reser_contact->id_contact = $contactos->id;
                $reser_contact->save();
            }

            if($request->input('array_group') != null && sizeof($request->input('array_group')) > 0){
                $groups = $request->input('array_group');
                
                
                for ($i=0; $i < sizeof($groups); $i++) { 

                    $group_contacto = new GroupContact();
                    $group_contacto->id_contact = $c_id;
                    $group_contacto->id_group = $groups[$i];
                    $group_contacto->save();
                }

                $contactos_groups = GroupContact::with('contacto')->with('grupo')->whereIn('id_group', $groups)->get();

                return $this->sendResponse($contactos_groups->toArray(), 'Contactos creados y asociados a los grupo(s) con éxito');
            }

            return $this->sendResponse($contactos->toArray(), 'Contactos creado con éxito');
        }       
        
    }

    

    /**
     *
     * @OA\Post(
     *   path="/api/auth/contact_save",
     *   summary="Create contacts by excel file",
     *   operationId="save_file_contacts",   
     *   tags={"Contacts"},      
     *   @OA\Response(
     *      response=200,
     *      ref="../Swagger/definitions.yaml#/components/responses/Success"
     *    ),
     *   @OA\Response(
     *      response=401,
     *      ref="../Swagger/definitions.yaml#/components/responses/Unauthorized"
     *    ),
     *   @OA\Response(
     *      response=500,
     *      ref="../Swagger/definitions.yaml#/components/responses/InternalServerError"
     *   ),
     *  )
     */
    public function save_file_contacts(Request $request){

        $list = $request->all();
        
        $error = array();
        $linea = 0;
        foreach ($list as $key) {
            $linea = $linea + 1;
            $validator = Validator::make($key, [
                'phone' => 'required|regex:/^[0-9]{7,15}$/|min:7|max:15',
                'info' => 'nullable|array'                
            ]);
            if($validator->fails()){
               array_push($error, array("Linea: ".$linea => $validator->errors()));       
            }  
        }
        
        if(sizeof($error) > 0){
            return $this->sendError($error, 'Existen errores en el valores enviados');
        }else{

            $user_now = JWTAuth::parseToken()->authenticate();
            $band = false;
            $arrayContact = array();
            $arrayContactBad = array();
            foreach ($list as $key){  

                $phonenumber = $this->verificationRepo->verifyPhonenumbers($key["phone"]);
                if(!$phonenumber){                    
                    array_push($arrayContactBad, $key["phone"]);                
                }else{

                    $search = $this->searchContact($key["phone"]);
                    if(!$search){

                        $contactos = new Contact();
                        $contactos->phone = $key["phone"];
                        $contactos->info_file = json_encode($key["info"]);
                        $contactos->status = 1;
                        $contactos->user_email = $user_now->email;
                        $contactos->save();

                        $contactos->info_file = json_decode($contactos->info_file);

                        array_push($arrayContact, $contactos->toArray());
                    }else{
                        
                        Contact::where('phone', $key["phone"])->update(['info_file' => json_encode($key["info"])]);
                        $cont = Contact::where('phone', $key["phone"])->first();
                        
                        $cont->info_file = json_decode($cont->info_file);


                        array_push($arrayContact, $cont->toArray());

                    }

                }
                
            }

            if(count($arrayContactBad) > 0){
                if(count($arrayContact) > 0){
                    $contacts_good = $arrayContact;
                    $contacts_bad = $arrayContactBad;
                    $contacts = compact('contacts_good', 'contacts_bad');
                    return $this->sendResponse($contacts, 'Contactos agregados y actualizados con éxito');
                } 
                $contacts_bad = $arrayContactBad;
                $contacts = compact('contacts_bad');
                return $this->sendResponse($contacts, 'Contactos con números inválidos');
            } 
        }
        

    }


    public function searchContact($phone){
        $contact = Contact::where('phone', $phone)->first();
        if (!$contact) {
            return false;
        }
        return true;
    }


     /**
     *
     * @OA\Get(
     *   path="/api/auth/contact/{contact}",
     *   summary="List the contacts of a specific user",
     *   operationId="show",   
     *   tags={"Contacts"}, 
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/email"
     *    ),    
     *   @OA\Response(
     *      response=200,
     *      ref="../Swagger/definitions.yaml#/components/responses/Success"
     *    ),
     *   @OA\Response(
     *      response=401,
     *      ref="../Swagger/definitions.yaml#/components/responses/Unauthorized"
     *    ),
     *   @OA\Response(
     *      response=500,
     *      ref="../Swagger/definitions.yaml#/components/responses/InternalServerError"
     *   ),
     *  )
     */
    public function show($email)
    {
        
        $contact = Contact::where('user_email', $email)->get();
        if (is_null($contact)) {
            return $this->sendError('Contacto no encontrado');
        }
        $contact_array = array();
        foreach ($contact as $key) {
            $groups = $this->groups_contact($key->id);
            $ar = array("id" => $key->id,"email" => $key->email, "phone" => $key->phone, "first_name" => $key->first_name, "last_name" => $key->last_name, "born_date" => (!is_null($key->born_date)) ? date_format($key->born_date, "d-m-Y") : null, "gender" => $key->gender, "info_file" => json_decode($key->info_file), "status" => $key->status,"groups" => $groups );
            array_push($contact_array, $ar);
        }
        
        return $this->sendResponse($contact_array, 'Contactos por usuario devuelto con éxito');
    }


    public function groups_contact($id_contact){

        $groups = GroupContact::with('grupo')->where('id_contact','=',$id_contact)
                                ->get();
        return $groups;
    }




    /**
     *
     * @OA\Get(
     *   path="/api/auth/groupsContactUser",
     *   summary="List of groups for user and number of contacts.",
     *   operationId="groupsContactUser",   
     *   tags={"Contacts"},         
     *   @OA\Response(
     *      response=200,
     *      ref="../Swagger/definitions.yaml#/components/responses/Success"
     *    ),
     *   @OA\Response(
     *      response=401,
     *      ref="../Swagger/definitions.yaml#/components/responses/Unauthorized"
     *    ),
     *   @OA\Response(
     *      response=500,
     *      ref="../Swagger/definitions.yaml#/components/responses/InternalServerError"
     *   ),
     *  )
     */
    public function groupsContactUser()
    {
        
        $user_now = JWTAuth::parseToken()->authenticate();        
        $emailUser = $user_now->email;
        
        $group = Contact::with('groups')
                    ->where('user_email', $emailUser)
                    ->get();
       
        $arrayIds = array();
        
        foreach ($group as $key) {  
            
                  
            if(sizeof($key->groups) > 0 ){
                foreach($key->groups as $vkey){
                    if($this->searh_group_array($vkey->id , $arrayIds) == true){
                    
                    for ($i=0; $i < sizeof($arrayIds); $i++){
                    
                        if((int)$arrayIds[$i]["id_group"] == (int)$vkey->id){
                            $arrayIds[$i]["cantidad"] = ($arrayIds[$i]["cantidad"] + 1);                            
                        }
                    }

                    }else{
                        array_push($arrayIds, array('id_group' => $vkey->id, 'descrip_group' => $vkey->description, 'cantidad' => 1) );
                    }

                }
                
            }
        }
        
        if (is_null($arrayIds)) {
            return $this->sendError('Grupos no encontrados');
        }
        return $this->sendResponse($arrayIds, 'Grupos devueltos con éxito');
    }

    public function searh_group_array($id_group, $arrayAll){
        
        if( count($arrayAll) > 0){
            for ($i=0; $i < sizeof($arrayAll); $i++) {            
                if($arrayAll[$i]["id_group"] == $id_group){
                    return true;
                }
            }
            return false;
        }
        return false;

    }


    /**
     *
     * @OA\Put(
     *   path="/api/auth/contact/{contact}",
     *   summary="update a specific contact",
     *   operationId="update",   
     *   tags={"Contacts"}, 
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/id"
     *    ), 
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/email_contact"
     *    ), 
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/phone"
     *    ),
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/first_name"
     *    ),
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/last_name"
     *    ),
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/born_date"
     *    ), 
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/date_reservation"
     *    ), 
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/gender"
     *    ),    
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/status"
     *    ),    
     *   @OA\Response(
     *      response=200,
     *      ref="../Swagger/definitions.yaml#/components/responses/Success"
     *    ),
     *   @OA\Response(
     *      response=401,
     *      ref="../Swagger/definitions.yaml#/components/responses/Unauthorized"
     *    ),
     *   @OA\Response(
     *      response=500,
     *      ref="../Swagger/definitions.yaml#/components/responses/InternalServerError"
     *   ),
     *  )
     */
    public function update(CreateRequest $request, $id)
    {
        $input = $request->validated();
        
        $Contacto = Contact::find($id);        
        if (is_null($contact)) {
            return $this->sendError('Contacto no encontrado');
        }

        $phonenumber = $this->verificationRepo->verifyPhonenumbers($input['phone']);
        if(!$phonenumber){
            return $this->sendError('Número no válido: '.$input['phone']);
        }

        $fecha_reser_old = Reservation::where('id_contact', $id)
                        ->where('reservation_date', $input['reservation_date'])
                        ->first();
        if(!$fecha_reser_old){

            $reser_contact = new Reservation();
            $reser_contact->reservation_date = $input['reservation_date'];
            $reser_contact->id_contact = $id;
            $reser_contact->save();

        }

        $Contacto->email = $input['email_contact'];
        $Contacto->phone = $input['phone'];
        $Contacto->first_name = $input['first_name'];
        $Contacto->last_name = $input['last_name'];
        $Contacto->born_date = $input['born_date'];
        $Contacto->status = $input['status'];        
        $Contacto->gender = $input['gender'];
        $Contacto->save();



        return $this->sendResponse($Contacto->toArray(), 'Contacto actualizado con éxito');

    }


    /**
     *
     * @OA\Delete(
     *   path="/api/auth/contact/{contact}",
     *   summary="Delete the contact",
     *   operationId="destroy",   
     *   tags={"Contacts"}, 
     *   @OA\Parameter(
     *      ref="../Swagger/definitions.yaml#/components/parameters/id"
     *    ),    
     *   @OA\Response(
     *      response=200,
     *      ref="../Swagger/definitions.yaml#/components/responses/Success"
     *    ),
     *   @OA\Response(
     *      response=401,
     *      ref="../Swagger/definitions.yaml#/components/responses/Unauthorized"
     *    ),
     *   @OA\Response(
     *      response=500,
     *      ref="../Swagger/definitions.yaml#/components/responses/InternalServerError"
     *   ),
     *  )
     */
    public function destroy($id)
    {        

        try {
            $contacto = Contact::find($id);
            if (is_null($contacto)) {
                return $this->sendError('Contacto no encontrado');
            }
            $contacto->delete();
            return $this->sendResponse($contacto->toArray(), 'Contacto eliminado con éxito');

        }catch (\Illuminate\Database\QueryException $e){
            return response()->json(['error' => 'El Contacto no se puedo eliminar, es usado en otra tabla', 'exception' => $e->errorInfo], 400);
        }
        
    }
}
